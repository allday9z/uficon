<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\ImportColumnMap;
use App\Models\ImportProfile;
use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use OpenSpout\Reader\XLSX\Reader;

class ImportProductsPage extends Page
{

    protected static string $resource = ProductResource::class;

    protected string $view = 'filament.resources.products.pages.import-products-page';

    protected static ?string $title = 'Import Products';

    protected static ?string $navigationLabel = 'Import';

    // ── Livewire state ───────────────────────────────────────────────────

    public string $step = 'upload'; // upload | preview | done

    public array $formData = [];

    /** Parsed preview rows for display */
    public array $previewRows = [];

    /** Summary counters */
    public int $previewCreate  = 0;
    public int $previewUpdate  = 0;
    public int $previewSkip    = 0;

    /** Import result message */
    public string $resultMessage = '';

    /** Stored temp file path (storage-relative) */
    public ?string $tempPath = null;

    /** Processed form state (profile, fresh, skip_existing) */
    public array $importOptions = [];

    /** Column maps for the selected profile (for display on step 1) */
    public array $profileMaps = [];

    public bool $showMappings = false;

    public function mount(): void
    {
        $this->form->fill([
            'profile'       => 'caas',
            'file'          => null,
            'fresh'         => false,
            'skip_existing' => false,
        ]);
        $this->loadProfileMaps('caas');
    }

    public function loadProfileMaps(string $slug): void
    {
        $this->profileMaps = ImportColumnMap::whereHas('profile', fn ($q) => $q->where('ip_slug', $slug))
            ->orderBy('icm_position')
            ->get()
            ->map(fn ($m) => [
                'header'  => $m->icm_source_header ?? "col[{$m->icm_source_index}]",
                'index'   => $m->icm_source_index,
                'model'   => $m->icm_target_model,
                'field'   => $m->icm_target_field,
                'mode'    => $m->icm_update_mode,
                'required'=> $m->icm_required,
            ])
            ->toArray();
    }

    public function updatedFormDataProfile(string $value): void
    {
        $this->loadProfileMaps($value);
        $this->showMappings = false;
    }

    // ── Form ─────────────────────────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        $profiles = ImportProfile::pluck('ip_name', 'ip_slug')->toArray();

        return $schema
            ->components([
                Select::make('profile')
                    ->label('Import Profile')
                    ->options($profiles)
                    ->default('caas')
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (?string $state) => $state ? $this->updatedFormDataProfile($state) : null),

                FileUpload::make('file')
                    ->label('File (.xlsx)')
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->required()
                    ->disk('local')
                    ->directory('imports/products')
                    ->preserveFilenames(),

                Toggle::make('fresh')
                    ->label('Fresh (delete existing before import)')
                    ->default(false),

                Toggle::make('skip_existing')
                    ->label('Skip existing (insert new only)')
                    ->default(false),
            ])
            ->statePath('formData');
    }

    // ── Actions ──────────────────────────────────────────────────────────

    public function doPreview(): void
    {
        $state       = $this->form->getState(); // validates + processes file upload
        $profileSlug = $state['profile'] ?? 'caas';
        $fileValue   = $state['file'] ?? null;
        $fileRelPath = is_array($fileValue) ? ($fileValue[0] ?? null) : $fileValue;

        $filePath = null;
        if ($fileRelPath) {
            if (str_starts_with((string) $fileRelPath, 'livewire-file:')) {
                $tmpName  = substr((string) $fileRelPath, strlen('livewire-file:'));
                $filePath = TemporaryUploadedFile::createFromLivewire($tmpName)->getRealPath();
            } else {
                $filePath = Storage::disk('local')->path($fileRelPath);
            }
        }

        if (! $filePath || ! file_exists($filePath)) {
            Notification::make()
                ->title('File not found')
                ->body('ref: ' . ($fileRelPath ?? 'null'))
                ->danger()->send();
            return;
        }

        $profile = ImportProfile::where('ip_slug', $profileSlug)->with('columnMaps')->first();
        if (! $profile) {
            Notification::make()->title("Profile not found: {$profileSlug}")->danger()->send();
            return;
        }

        // Store temp file to permanent location so it survives until doImport()
        if (str_starts_with((string) $fileRelPath, 'livewire-file:')) {
            $tmpName  = substr((string) $fileRelPath, strlen('livewire-file:'));
            $tmp      = TemporaryUploadedFile::createFromLivewire($tmpName);
            $stored   = $tmp->storeAs('imports/products', $tmp->getClientOriginalName(), 'local');
            $this->tempPath = $stored;
        } else {
            $this->tempPath = $fileRelPath;
        }
        $this->importOptions = [
            'profile'       => $profileSlug,
            'fresh'         => $state['fresh'] ?? false,
            'skip_existing' => $state['skip_existing'] ?? false,
        ];
        $this->previewRows = [];
        $this->previewCreate = $this->previewUpdate = $this->previewSkip = 0;

        // Build map index
        $mapIndex = [];
        foreach ($profile->columnMaps as $map) {
            $mapIndex["{$map->icm_target_model}.{$map->icm_target_field}"] = $map;
        }

        $get = function (array $row, string $model, string $field) use ($mapIndex): ?string {
            $map = $mapIndex["{$model}.{$field}"] ?? null;
            if (! $map || $map->icm_source_index === null) {
                return null;
            }
            $val = $row[$map->icm_source_index] ?? null;
            return ($val !== null && $val !== '') ? trim((string) $val) : null;
        };

        // Read product rows
        $productRows = $this->readProductRows($filePath, $profile->ip_sheet_name, $profile->ip_header_row);

        // Group by primary_title
        $groups = [];
        foreach ($productRows as $row) {
            $title = $get($row, 'Product', 'pd_primary_title');
            if ($title) {
                $groups[$title][] = $row;
            }
        }

        $isFresh      = $state['fresh'] ?? false;
        $skipExisting = $state['skip_existing'] ?? false;

        foreach ($groups as $title => $rows) {
            $handle   = Str::slug($title);
            $existing = Product::withTrashed()->where('pd_handle', $handle)->first();

            if (! $existing) {
                $action = 'CREATE';
                $detail = count($rows) . ' variants (new)';
                $this->previewCreate++;
            } elseif ($skipExisting) {
                $action = 'SKIP';
                $detail = 'already exists';
                $this->previewSkip++;
            } elseif ($isFresh) {
                $action = 'DELETE→CREATE';
                $detail = count($rows) . ' variants (fresh)';
                $this->previewUpdate++;
            } else {
                $newV = $updV = 0;
                foreach ($rows as $row) {
                    $mpn = $get($row, 'ProductVariant', 'pv_mpn');
                    if ($mpn) {
                        ProductVariant::withTrashed()->where('pv_mpn', $mpn)->exists() ? $updV++ : $newV++;
                    }
                }
                $parts = array_filter([
                    $newV > 0  ? "+{$newV} new variants" : null,
                    $updV > 0  ? "~{$updV} updated" : null,
                    $newV === 0 && $updV === 0 ? 'no changes' : null,
                ]);
                $action = 'UPDATE';
                $detail = implode(', ', $parts) ?: count($rows) . ' variants';
                $this->previewUpdate++;
            }

            $this->previewRows[] = [
                'action'   => $action,
                'title'    => $title,
                'variants' => count($rows),
                'detail'   => $detail,
            ];
        }

        $this->step = 'preview';
    }

    public function doImport(): void
    {
        if (! $this->tempPath) {
            Notification::make()->title('No file — go back and upload again')->danger()->send();
            return;
        }

        $opts        = $this->importOptions;
        $profileSlug = $opts['profile'] ?? 'caas';
        $filePath    = Storage::disk('local')->path($this->tempPath);

        $args = ['file' => $filePath, '--profile' => $profileSlug];
        if ($opts['fresh'] ?? false)         $args['--fresh']          = true;
        if ($opts['skip_existing'] ?? false) $args['--skip-existing']  = true;

        $exitCode = Artisan::call('products:import', $args);
        $output   = Artisan::output();

        preg_match('/created (\d+), updated (\d+), skipped (\d+)/', $output, $m);
        $created = (int) ($m[1] ?? 0);
        $updated = (int) ($m[2] ?? 0);
        $skipped = (int) ($m[3] ?? 0);

        if ($exitCode === 0) {
            $this->resultMessage = "Import complete — created {$created}, updated {$updated}, skipped {$skipped}";
            $this->step = 'done';
            Notification::make()->title($this->resultMessage)->success()->send();
        } else {
            Notification::make()
                ->title('Import failed')
                ->body(strip_tags(substr($output, 0, 400)))
                ->danger()
                ->send();
        }
    }

    public function goBack(): void
    {
        $this->step = 'upload';
        $this->previewRows = [];
    }

    public function resetForm(): void
    {
        $this->step = 'upload';
        $this->previewRows = [];
        $this->resultMessage = '';
        $this->tempPath = null;
        $this->form->fill();
    }

    // ── Sheet reader ─────────────────────────────────────────────────────

    private function readProductRows(string $filePath, ?string $sheetFilter, int $headerRow): array
    {
        $reader = new Reader();
        $reader->open($filePath);
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $name = $sheet->getName();
            $isTarget = $sheetFilter
                ? str_contains($name, $sheetFilter) && ! str_contains($name, 'Collection')
                : ! str_contains($name, 'Collection');

            if (! $isTarget) continue;

            $rowIndex = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $rowIndex++;
                if ($rowIndex <= $headerRow) continue;
                $cells = array_map(fn ($c) => $c->getValue(), $row->getCells());
                $rows[] = array_pad($cells, 50, null);
            }
        }

        $reader->close();
        return $rows;
    }
}
