<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Jobs\ScrapeGalleryJob;
use App\Models\Product;
use App\Models\ProductGallery;
use App\Models\ProductMedia;
use Filament\Actions\Action;
use Filament\Actions\Action as FormAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ManageProductGalleries extends ManageRelatedRecords
{
    protected static string $resource = ProductResource::class;
    protected static string $relationship = 'galleries';
    protected static ?string $navigationLabel = 'Galleries';

    // Livewire polling state for scrape progress
    public ?array $scrapeProgress = null;

    /**
     * Read fresh progress from Cache on every Livewire render cycle.
     * Called directly from ->description() closure so each poll gets live data.
     */
    private function refreshScrapeProgress(): void
    {
        $product = $this->getOwnerRecord();
        if (! $product) return;
        $key  = "scrape_gallery_{$product->pd_id}";
        $data = Cache::get($key);

        if (! $data) {
            $this->scrapeProgress = null;
            return;
        }

        $this->scrapeProgress = $data;

        if (($data['status'] ?? '') === 'done') {
            Notification::make()
                ->title("✅ Scrape complete — {$data['done']} colors, {$data['images']} images")
                ->success()
                ->send();
            Cache::forget($key);
            $this->scrapeProgress = null;
        }
    }

    /** Polling active while cache key exists — checked every render */
    public function getPollingInterval(): ?string
    {
        $product = $this->getOwnerRecord();
        if (! $product) return null;
        return Cache::has("scrape_gallery_{$product->pd_id}") ? '2000ms' : null;
    }

    private function scrapeProgressHtml(): HtmlString
    {
        if (! $this->scrapeProgress) return new HtmlString('');
        $p      = $this->scrapeProgress;
        $status = $p['status'] ?? 'processing';
        $total  = max($p['total'] ?? 1, 1);
        $done   = $p['done'] ?? 0;
        $pct    = (int) round($done / $total * 100);
        $color  = $status === 'failed' ? '#ef4444' : '#0071e3';
        $current = e($p['current'] ?? '');
        $images  = $p['images'] ?? 0;
        $errors  = $p['errors'] ?? [];

        if ($status === 'failed') {
            return new HtmlString('<div style="margin:12px 0;padding:12px 16px;background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;">❌ Scrape failed: ' . e($p['error'] ?? '') . '</div>');
        }

        $html  = '<div style="margin:10px 0 14px;font-family:system-ui,sans-serif;">';
        $html .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:7px;">';
        $html .= '<span style="font-size:13px;font-weight:600;color:#f5f5f7;">⬇ Scraping galleries…</span>';
        $html .= '<span style="font-size:12px;color:#aeaeb2;">' . $done . ' / ' . $total . ' colors&nbsp;&bull;&nbsp;' . $images . ' images</span>';
        $html .= '</div>';
        $html .= '<div style="background:#3a3a3c;border-radius:100px;height:7px;overflow:hidden;">';
        $html .= '<div style="background:' . $color . ';height:100%;width:' . $pct . '%;border-radius:100px;transition:width .5s ease;min-width:' . ($pct > 0 ? '0' : '6px') . ';"></div>';
        $html .= '</div>';
        if ($current) {
            $html .= '<div style="margin-top:6px;font-size:12px;color:#8e8e93;">Currently: <strong style="color:#e5e5ea;font-weight:500;">' . $current . '</strong></div>';
        }
        if (! empty($errors)) {
            $html .= '<div style="margin-top:5px;font-size:11px;color:#ff6b6b;">⚠ ' . count($errors) . ' error(s)</div>';
        }
        $html .= '</div>';

        return new HtmlString($html);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('pg_name')
                ->label('Gallery name')
                ->placeholder('Black, White Titanium, Desert Titanium…')
                ->required()
                ->maxLength(100)
                ->columnSpanFull(),

            // pg_slug auto-generated via ProductGallery::booted() on create; preserved as-is on edit
            Hidden::make('pg_slug'),

            // ── Existing images: drag-to-sort + delete ────────────────────
            Repeater::make('existing_media')
                ->label(fn ($record) => $record
                    ? 'Images (' . $record->media()->count() . ') — drag to sort, ✕ to remove'
                    : 'Images')
                ->columnSpanFull()
                ->reorderable()
                ->deletable()
                ->addable(false)
                ->schema([
                    Placeholder::make('row')
                        ->label('')
                        ->columnSpanFull()
                        ->content(function ($get): HtmlString {
                            $src  = $get('pm_src') ?? '';
                            $ext  = strtolower(pathinfo($src, PATHINFO_EXTENSION));
                            $name = basename(parse_url($src, PHP_URL_PATH));

                            $isVideo = in_array($ext, ['mp4', 'mov', 'webm']);
                            $thumb   = $isVideo
                                ? '<div style="width:60px;height:60px;background:#1f2937;border-radius:8px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:22px;">▶</div>'
                                : '<div class="gt" style="position:relative;flex-shrink:0;">
                                    <img class="t" src="' . e($src) . '" loading="lazy" style="width:60px;height:60px;object-fit:cover;border-radius:8px;cursor:zoom-in;"/>
                                    <img class="z" src="' . e($src) . '" loading="lazy" style="display:none;position:absolute;top:0;left:66px;z-index:9999;width:260px;height:260px;object-fit:contain;background:#111;border:1px solid #374151;border-radius:10px;box-shadow:0 16px 40px rgba(0,0,0,.9);"/>
                                   </div>';

                            $badge = $isVideo
                                ? ' <span style="font-size:10px;background:#374151;color:#9ca3af;padding:1px 5px;border-radius:4px;vertical-align:middle;">VIDEO</span>'
                                : '';

                            return new HtmlString('
<style>.gt:hover .t{opacity:.75;}.gt:hover .z{display:block!important;}</style>
<div style="display:flex;align-items:center;gap:12px;padding:4px 0;">
  ' . $thumb . '
  <div style="flex:1;min-width:0;font-size:13px;color:#d1d5db;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="' . e($src) . '">' . e($name) . $badge . '</div>
</div>');
                        }),
                    Hidden::make('pm_id'),
                    Hidden::make('pm_position'),
                    Hidden::make('pm_src'),
                    Hidden::make('pm_alt'),
                ])
                ->afterStateHydrated(function ($component, $record) {
                    if (! $record) { $component->state([]); return; }
                    $items = $record->media()->orderBy('pm_position')->get()
                        ->map(fn ($m) => [
                            'pm_id'       => $m->pm_id,
                            'pm_src'      => $m->pm_src,
                            'pm_alt'      => $m->pm_alt,
                            'pm_position' => $m->pm_position,
                        ])
                        ->toArray();
                    $component->state($items);
                })
                ->visible(fn ($record) => $record !== null),

            // ── Upload new images ─────────────────────────────────────────
            FileUpload::make('gallery_images')
                ->label(fn ($record) => $record ? 'Add images / videos' : 'Images / Videos')
                ->helperText('JPG · PNG · WebP · AVIF · MP4 — สูงสุด 50 ไฟล์, 50MB/ไฟล์')
                ->disk('public')
                ->directory('products/galleries')
                ->multiple()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif', 'video/mp4', 'video/quicktime', 'video/webm'])
                ->maxSize(51200)
                ->maxFiles(50)
                ->panelLayout('grid')
                ->columnSpanFull(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scrape_istudio')
                ->label('Scrape all colors')
                ->icon(Heroicon::ArrowDownTray)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Scrape galleries for all colors')
                ->modalDescription('ดาวน์โหลดรูปทุกสีใน background (สูงสุด 5 นาที) — try istudio.store → istudiobyspvi.com fallback — ProgressBar จะแสดงขณะทำงาน')
                ->action(function () {
                    $product = $this->getOwnerRecord();
                    if (! $product) { Notification::make()->title('Product not found')->danger()->send(); return; }
                    $product->loadMissing('variants');
                    if ($product->variants->isEmpty()) { Notification::make()->title('No variants found')->warning()->send(); return; }

                    $colorCount = $product->variants->whereNotNull('pv_option1')->unique('pv_option1')->count();

                    // Init cache progress before dispatching
                    Cache::put("scrape_gallery_{$product->pd_id}", [
                        'status' => 'processing', 'total' => $colorCount,
                        'done' => 0, 'images' => 0, 'current' => 'กำลังเริ่ม…', 'errors' => [],
                    ], now()->addMinutes(10));

                    ScrapeGalleryJob::dispatch($product->pd_id);
                    $this->scrapeProgress = Cache::get("scrape_gallery_{$product->pd_id}");

                    Notification::make()->title("Started — {$colorCount} colors queued for scraping")->success()->send();
                }),
        ];
    }


    protected function handleRecordCreation(array $data): Model
    {
        $images = array_values($data['gallery_images'] ?? []);
        unset($data['gallery_images'], $data['existing_media']);

        /** @var ProductGallery $gallery */
        $gallery = $this->getOwnerRecord()->galleries()->create($data);

        foreach ($images as $position => $path) {
            $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $type = in_array($ext, ['mp4', 'mov', 'webm']) ? 'video' : 'image';
            ProductMedia::create([
                'pd_id'       => $this->getOwnerRecord()->pd_id,
                'pg_id'       => $gallery->pg_id,
                'pm_src'      => Storage::disk('public')->url($path),
                'pm_type'     => $type,
                'pm_position' => $position + 1,
                'pm_alt'      => $gallery->pg_name,
            ]);
        }

        return $gallery;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $images      = $data['gallery_images'] ?? [];
        $mediaItems  = $data['existing_media'] ?? null; // from Repeater
        unset($data['gallery_images'], $data['existing_media']);

        $record->update($data);

        // Process existing media: delete removed items + update positions + alt
        if ($mediaItems !== null) {
            $keptIds = collect($mediaItems)->pluck('pm_id')->filter()->values()->toArray();
            // Only delete if we have explicit IDs to keep — guard against empty array deleting all
            if (! empty($keptIds)) {
                ProductMedia::where('pg_id', $record->pg_id)->whereNotIn('pm_id', $keptIds)->delete();
            }
            // Update position + alt for remaining items
            foreach ($mediaItems as $position => $item) {
                if (! empty($item['pm_id'])) {
                    ProductMedia::where('pm_id', $item['pm_id'])->update([
                        'pm_position' => $position + 1,
                        'pm_alt'      => $item['pm_alt'] ?? $record->pg_name,
                    ]);
                }
            }
        }

        $images = array_values($images);
        if (! empty($images)) {
            $nextPosition = $record->media()->max('pm_position') + 1;
            foreach ($images as $i => $path) {
                $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $type = in_array($ext, ['mp4', 'mov', 'webm']) ? 'video' : 'image';
                ProductMedia::create([
                    'pd_id'       => $this->getOwnerRecord()->pd_id,
                    'pg_id'       => $record->pg_id,
                    'pm_src'      => Storage::disk('public')->url($path),
                    'pm_type'     => $type,
                    'pm_position' => $nextPosition + $i,
                    'pm_alt'      => $record->pg_name,
                ]);
            }

            Notification::make()
                ->success()
                ->title('Added ' . count($images) . ' image(s) to ' . $record->pg_name)
                ->send();
        }

        return $record;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('pg_name')
            ->columns([
                TextColumn::make('pg_name')
                    ->label('Gallery name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('pg_slug')
                    ->label('Slug')
                    ->color('gray'),

                TextColumn::make('media_count')
                    ->label('Images')
                    ->getStateUsing(fn (ProductGallery $record): int => $record->media()->count())
                    ->badge()
                    ->color('info'),

                TextColumn::make('variants_count')
                    ->label('Variants using')
                    ->getStateUsing(fn (ProductGallery $record): int => $record->variants()->count())
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),

                TextColumn::make('pg_position')
                    ->label('#')
                    ->sortable(),
            ])
            ->defaultSort('pg_position')
            ->description(function (): HtmlString {
                $this->refreshScrapeProgress(); // reads fresh data from Cache every render cycle
                return $this->scrapeProgress ? $this->scrapeProgressHtml() : new HtmlString('');
            })
            ->headerActions([
                CreateAction::make()
                    ->label('+ New gallery')
                    ->modalWidth('2xl')
                    ->using(fn (array $data) => $this->handleRecordCreation($data)),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth('2xl')
                    ->using(fn (Model $record, array $data) => $this->handleRecordUpdate($record, $data)),

                \Filament\Actions\Action::make('scrape_one')
                    ->label('Scrape')
                    ->icon(Heroicon::ArrowDownTray)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading(fn (ProductGallery $record) => "Scrape '{$record->pg_name}'")
                    ->modalDescription('ดาวน์โหลดรูปใน background — progress bar จะปรากฏขณะทำงาน')
                    ->action(function (ProductGallery $record) {
                        $product = $this->getOwnerRecord();

                        Cache::put("scrape_gallery_{$product->pd_id}", [
                            'status' => 'processing', 'total' => 1, 'done' => 0,
                            'images' => 0, 'current' => $record->pg_name, 'errors' => [],
                        ], now()->addMinutes(10));

                        ScrapeGalleryJob::dispatch($product->pd_id, $record->pg_id);
                        $this->scrapeProgress = Cache::get("scrape_gallery_{$product->pd_id}");

                        Notification::make()->title("Queued — scraping '{$record->pg_name}'")->success()->send();
                    }),

                DeleteAction::make()
                    ->before(function (ProductGallery $record) {
                        $record->variants()->update(['pg_id' => null]);
                        $record->media()->update(['pg_id' => null]);
                    }),
            ])
            ->reorderable('pg_position');
    }
}
