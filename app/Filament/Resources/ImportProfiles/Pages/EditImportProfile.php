<?php

namespace App\Filament\Resources\ImportProfiles\Pages;

use App\Filament\Resources\ImportProfiles\ImportProfileResource;
use App\Models\ImportColumnMap;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use OpenSpout\Reader\XLSX\Reader;

class EditImportProfile extends EditRecord
{
    protected static string $resource = ImportProfileResource::class;

    // ── Field hint map: xlsx header → [model, field, update_mode] ────────
    private const FIELD_HINTS = [
        'LOB'                           => ['Product',           'pd_lob',             'always'],
        'Sub LOB'                       => ['Product',           'pd_sub_lob',         'always'],
        'Mpn'                           => ['ProductVariant',    'pv_mpn',             'always'],
        'Barcode'                       => ['ProductVariant',    'pv_barcode',         'always'],
        'Handle'                        => ['ProductVariant',    'pv_handle',          'always'],
        'Title'                         => ['ProductVariant',    'pv_caas_title',      'always'],
        'Primary display title'         => ['Product',           'pd_primary_title',   'always'],
        'Secondary display title'       => ['Product',           'pd_secondary_title', 'always'],
        'Product type'                  => ['Product',           'pd_type',            'always'],
        'Vendor'                        => ['Brand',             'brand_name',         'always'],
        'Description'                   => ['Product',           'pd_description',     'always'],
        'Features'                      => ['Product',           'pd_features',        'always'],
        'Base product name'             => ['Product',           'pd_base_name',       'always'],
        'Base product collection'       => ['ProductCollection', 'pcol_handle',        'always'],
        'Option 1 Value'                => ['ProductVariant',    'pv_option1',         'always'],
        'Option 2 Value'                => ['ProductVariant',    'pv_option2',         'always'],
        'Option 3 Value'                => ['ProductVariant',    'pv_option3',         'always'],
        'Option 4 Value'                => ['ProductVariant',    'pv_option4',         'always'],
        'Option 5 Value'                => ['ProductVariant',    'pv_option5',         'always'],
        'Option 6 Value'                => ['ProductVariant',    'pv_option6',         'always'],
        'Option 7 Value'                => ['ProductVariant',    'pv_option7',         'always'],
        'In the box 1'                  => ['ProductInbox',      'inbox1',             'always'],
        'In the box 2'                  => ['ProductInbox',      'inbox2',             'always'],
        'In the box 3'                  => ['ProductInbox',      'inbox3',             'always'],
        'In the box 4'                  => ['ProductInbox',      'inbox4',             'always'],
        "Manufacturer's warranty parts" => ['Product',           'pd_warranty_parts',  'always'],
        "Manufacturer's warranty labor" => ['Product',           'pd_warranty_labor',  'always'],
        'Color swatch image file'       => ['ProductVariant',    'pv_color_swatch',    'always'],
        'Band color swatch image file'  => ['ProductVariant',    'pv_band_swatch',     'always'],
        'Product image 1'               => ['ProductMedia',      'img1',               'always'],
        'Product image 2'               => ['ProductMedia',      'img2',               'always'],
        'Product image 3'               => ['ProductMedia',      'img3',               'always'],
        'Product image 4'               => ['ProductMedia',      'img4',               'always'],
        'Product image 5'               => ['ProductMedia',      'img5',               'always'],
        'Product image 6'               => ['ProductMedia',      'img6',               'always'],
        'Product image 7'               => ['ProductMedia',      'img7',               'always'],
        'Product image 8'               => ['ProductMedia',      'img8',               'always'],
        'Product image 9'               => ['ProductMedia',      'img9',               'always'],
        'Product image 10'              => ['ProductMedia',      'img10',              'always'],
        'Product image 11'              => ['ProductMedia',      'img11',              'always'],
        'Product image 12'              => ['ProductMedia',      'img12',              'always'],
        'Product image 13'              => ['ProductMedia',      'img13',              'always'],
        'Product image 14'              => ['ProductMedia',      'img14',              'always'],
        'Product image 15'              => ['ProductMedia',      'img15',              'always'],
        'Video asset 1'                 => ['ProductMedia',      'video1',             'always'],
        'Video asset 2'                 => ['ProductMedia',      'video2',             'always'],
        'Video asset 3'                 => ['ProductMedia',      'video3',             'always'],
        'Price'                         => ['ProductVariant',    'price',              'create_only'],
        'Variant Price'                 => ['ProductVariant',    'price',              'create_only'],
    ];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('autoDetect')
                ->label('Auto-detect from File')
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->color('info')
                ->modalHeading('Auto-detect Column Headers')
                ->modalDescription('Upload a sample .xlsx file — row 1 headers will be matched to target fields automatically.')
                ->modalSubmitActionLabel('Detect & Create Maps')
                ->schema([
                    FileUpload::make('sample_file')
                        ->label('Upload sample .xlsx file')
                        ->helperText('Row 1 (header row) will be read to detect column names.')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->required()
                        ->disk('local')
                        ->directory('imports/temp')
                        ->visibility('private'),

                    Select::make('on_conflict')
                        ->label('If maps already exist')
                        ->options([
                            'skip'    => 'Keep existing — only add new columns',
                            'replace' => 'Replace all — delete existing maps first',
                        ])
                        ->default('skip')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $fileValue   = $data['sample_file'] ?? null;
                    $fileRelPath = is_array($fileValue) ? ($fileValue[0] ?? null) : $fileValue;

                    if (! $fileRelPath) {
                        Notification::make()->title('No file provided')->danger()->send();
                        return;
                    }

                    if (str_starts_with((string) $fileRelPath, 'livewire-file:')) {
                        $tmpName  = substr($fileRelPath, strlen('livewire-file:'));
                        $filePath = TemporaryUploadedFile::createFromLivewire($tmpName)->getRealPath();
                    } else {
                        $filePath = \Illuminate\Support\Facades\Storage::disk('local')->path($fileRelPath);
                    }

                    if (! file_exists($filePath)) {
                        Notification::make()->title('File not found')->danger()->send();
                        return;
                    }

                    $profile    = $this->getRecord();
                    $headerRow  = $profile->ip_header_row ?? 1;
                    $headers    = $this->readHeaders($filePath, $headerRow);

                    if (empty($headers)) {
                        Notification::make()->title('No headers found in file')->warning()->send();
                        return;
                    }

                    if ($data['on_conflict'] === 'replace') {
                        ImportColumnMap::where('ip_id', $profile->ip_id)->delete();
                    }

                    $existingFields = ImportColumnMap::where('ip_id', $profile->ip_id)
                        ->pluck('icm_target_field')
                        ->toArray();

                    $created = 0;
                    $skipped = 0;

                    foreach ($headers as $index => $header) {
                        $hint = self::FIELD_HINTS[$header] ?? null;
                        [$model, $field, $mode] = $hint ?? ['Product', '', 'always'];

                        if ($data['on_conflict'] === 'skip' && in_array($field, $existingFields)) {
                            $skipped++;
                            continue;
                        }

                        ImportColumnMap::create([
                            'ip_id'             => $profile->ip_id,
                            'icm_source_header' => $header,
                            'icm_source_index'  => $index,
                            'icm_target_model'  => $model,
                            'icm_target_field'  => $field,
                            'icm_required'      => in_array($field, ['pd_primary_title', 'pv_mpn', 'pcol_handle']),
                            'icm_update_mode'   => $mode,
                            'icm_cast'          => $field === 'price' ? 'float' : null,
                            'icm_position'      => $index * 10,
                        ]);
                        $created++;
                    }

                    Notification::make()
                        ->title("Detected {$created} column maps")
                        ->body($skipped > 0 ? "Skipped {$skipped} existing maps." : "All maps created. Review target fields below.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['columnMaps']);
                }),

            DeleteAction::make(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            ...ImportProfileResource::form($schema)->getComponents(),

            Repeater::make('columnMaps')
                ->label('Column Mappings')
                ->relationship('columnMaps')
                ->schema(ImportProfileResource::columnMapSchema())
                ->orderColumn('icm_position')
                ->collapsible()
                ->collapsed()
                ->itemLabel(fn (array $state) => implode(' → ', array_filter([
                    $state['icm_source_header'] ?? ("col[{$state['icm_source_index']}]"),
                    $state['icm_target_model'] && $state['icm_target_field']
                        ? "{$state['icm_target_model']}.{$state['icm_target_field']}"
                        : null,
                    match ($state['icm_update_mode'] ?? 'always') {
                        'create_only' => '(create only)',
                        'skip'        => '(skip)',
                        default       => null,
                    },
                ])))
                ->addActionLabel('Add Column Map')
                ->columnSpanFull(),
        ]);
    }

    // ── Read header row from xlsx ─────────────────────────────────────────

    private function readHeaders(string $filePath, int $headerRow): array
    {
        $reader = new Reader();
        $reader->open($filePath);
        $headers = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $rowIndex = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $rowIndex++;
                if ($rowIndex === $headerRow) {
                    foreach ($row->getCells() as $cell) {
                        $val = trim((string) $cell->getValue());
                        $headers[] = $val !== '' ? $val : null;
                    }
                    break;
                }
            }
            break; // only first sheet
        }

        $reader->close();
        return $headers;
    }
}
