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
use Illuminate\Support\Str;

class ManageProductGalleries extends ManageRelatedRecords
{
    protected static string $resource = ProductResource::class;
    protected static string $relationship = 'galleries';
    protected static ?string $navigationLabel = 'Galleries';

    // Livewire polling state for scrape progress
    public ?array $scrapeProgress = null;

    /** Called by Livewire wire:poll every 2s while scraping */
    public function pollScrapeProgress(): void
    {
        $product = $this->getOwnerRecord();
        if (! $product) return;
        $key  = "scrape_gallery_{$product->pd_id}";
        $data = Cache::get($key);
        $this->scrapeProgress = $data;

        if ($data && ($data['status'] ?? '') === 'done') {
            Notification::make()
                ->title("✅ Scrape complete — {$data['done']} colors, {$data['images']} images")
                ->success()
                ->send();
            Cache::forget($key);
            $this->scrapeProgress = null;
        }
    }

    /** Livewire polling — active only when scraping */
    protected function getListeners(): array
    {
        return ['$refresh' => '$refresh'];
    }

    public function getPollingInterval(): ?string
    {
        return $this->scrapeProgress !== null ? '2000ms' : null;
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

        $html  = '<div style="margin:12px 0 16px;font-family:system-ui,sans-serif;">';
        $html .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">';
        $html .= '<span style="font-size:13px;font-weight:600;color:#1d1d1f;">⬇ Scraping galleries…</span>';
        $html .= '<span style="font-size:12px;color:#6e6e73;">' . $done . ' / ' . $total . ' colors • ' . $images . ' images</span>';
        $html .= '</div>';
        $html .= '<div style="background:#e5e5ea;border-radius:100px;height:8px;overflow:hidden;">';
        $html .= '<div style="background:' . $color . ';height:100%;width:' . $pct . '%;border-radius:100px;transition:width .4s ease;"></div>';
        $html .= '</div>';
        if ($current) {
            $html .= '<div style="margin-top:6px;font-size:12px;color:#6e6e73;">Currently: <strong style="color:#1d1d1f;">' . $current . '</strong></div>';
        }
        if (! empty($errors)) {
            $html .= '<div style="margin-top:6px;font-size:11px;color:#ef4444;">⚠ ' . count($errors) . ' error(s)</div>';
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
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, callable $set, callable $get) {
                    if (filled($state) && blank($get('pg_slug'))) {
                        $set('pg_slug', Str::slug($state));
                    }
                })
                ->columnSpanFull(),

            TextInput::make('pg_slug')
                ->label('Slug')
                ->placeholder('auto-filled from name')
                ->maxLength(100)
                ->columnSpanFull(),

            // ── Existing images: sortable + deletable Repeater ────────────
            Repeater::make('existing_media')
                ->label('Current images (drag to sort, ✕ to delete)')
                ->columnSpanFull()
                ->reorderable()
                ->deletable()
                ->addable(false)
                ->schema([
                    Placeholder::make('preview')
                        ->label('')
                        ->content(function ($get): HtmlString {
                            $src = $get('pm_src') ?? '';
                            $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
                            if (in_array($ext, ['mp4', 'mov', 'webm'])) {
                                return new HtmlString('<video src="' . e($src) . '" class="h-12 rounded" controls muted></video>');
                            }
                            // Small thumbnail + absolute hover zoom
                            return new HtmlString('
<style>
.gal-thumb-wrap{position:relative;display:inline-block;}
.gal-thumb-wrap img.thumb{width:56px;height:56px;object-fit:cover;border-radius:6px;border:1px solid #374151;cursor:zoom-in;transition:opacity .15s;}
.gal-thumb-wrap:hover img.thumb{opacity:.7;}
.gal-thumb-wrap .zoom{display:none;position:absolute;top:0;left:64px;z-index:9999;width:240px;height:240px;object-fit:contain;background:#111;border:2px solid #6b7280;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.7);}
.gal-thumb-wrap:hover .zoom{display:block;}
</style>
<div class="gal-thumb-wrap">
  <img class="thumb" src="' . e($src) . '" loading="lazy" />
  <img class="zoom" src="' . e($src) . '" loading="lazy" />
</div>');
                        }),
                    TextInput::make('pm_alt')->label('Alt text')->maxLength(200),
                    TextInput::make('pm_src')->label('URL')->disabled()->extraInputAttributes(['class' => 'text-xs text-gray-400']),
                    TextInput::make('pm_id')->hidden(),
                    TextInput::make('pm_position')->hidden(),
                ])
                ->afterStateHydrated(function ($component, $record) {
                    if (! $record) { $component->state([]); return; }
                    $items = $record->media()->orderBy('pm_position')->get()
                        ->map(fn ($m) => ['pm_id' => $m->pm_id, 'pm_src' => $m->pm_src, 'pm_alt' => $m->pm_alt, 'pm_position' => $m->pm_position])
                        ->toArray();
                    $component->state($items);
                })
                ->visible(fn ($record) => $record !== null),

            // ── Multi-file drop zone ──────────────────────────────────────
            FileUpload::make('gallery_images')
                ->label(fn ($record) => $record ? 'Add more images / videos' : 'Images / Videos')
                ->helperText('Drag & drop หรือคลิกเพื่อเลือกหลายไฟล์พร้อมกัน — รองรับ JPG, PNG, WebP, AVIF, MP4')
                ->disk('public')
                ->directory('products/galleries')
                ->multiple()
                ->reorderable()
                ->image()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif', 'video/mp4', 'video/quicktime', 'video/webm'])
                ->maxSize(51200)
                ->maxFiles(50)
                ->imagePreviewHeight('100')
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

    // ── Scraper ──────────────────────────────────────────────────────────

    /** Scrape exactly ONE gallery (1 color) — stays under 30s limit */
    private function scrapeOneGallery(\App\Models\Product $product, ProductGallery $gallery): int
    {
        $colorName = $gallery->pg_name;
        $variant   = $product->variants->first(fn ($v) => $v->pv_option1 === $colorName)
            ?? $product->variants->first();
        if (! $variant?->pv_handle) return 0;

        $url  = "https://www.istudio.store/products/{$variant->pv_handle}.json";
        $ctx  = stream_context_create(['http' => ['timeout' => 8, 'header' => "User-Agent: Mozilla/5.0\r\n"]]);
        $json = @file_get_contents($url, false, $ctx);
        if (! $json) return 0;

        $shopifyImages = json_decode($json, true)['product']['images'] ?? [];
        if (empty($shopifyImages)) return 0;

        ProductMedia::where('pd_id', $product->pd_id)
            ->where('pg_id', $gallery->pg_id)
            ->whereIn('pm_type', ['image', 'video'])
            ->delete();

        $dir      = "products/{$product->pd_handle}/{$gallery->pg_slug}";
        $position = 1;
        foreach ($shopifyImages as $img) {
            $srcUrl   = $img['src'] ?? '';
            if (! $srcUrl) continue;
            $contents = @file_get_contents($srcUrl, false, $ctx);
            if (! $contents) continue;
            $ext      = pathinfo(parse_url($srcUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $filename = sprintf('%s/%03d.%s', $dir, $position, $ext);
            Storage::disk('public')->put($filename, $contents);
            ProductMedia::create([
                'pd_id'       => $product->pd_id,
                'pg_id'       => $gallery->pg_id,
                'pm_src'      => Storage::disk('public')->url($filename),
                'pm_type'     => 'image',
                'pm_position' => $position,
                'pm_alt'      => $colorName,
            ]);
            $position++;
        }
        return $position - 1;
    }

    private function scrapeIstudioGalleries(\App\Models\Product $product): array
    {
        $totalGalleries = 0;
        $totalImages    = 0;
        $seenColors     = [];

        foreach ($product->variants as $variant) {
            if (! $variant->pv_handle || ! $variant->pv_option1) continue;

            $colorName = $variant->pv_option1;
            if (isset($seenColors[$colorName])) continue;
            $seenColors[$colorName] = true;

            // Fetch product JSON from istudio.store
            $url  = "https://www.istudio.store/products/{$variant->pv_handle}.json";
            $json = @file_get_contents($url, false, stream_context_create([
                'http' => ['timeout' => 8, 'header' => "User-Agent: Mozilla/5.0\r\n"],
            ]));
            if (! $json) continue;

            $data = json_decode($json, true);
            $shopifyImages = $data['product']['images'] ?? [];
            if (empty($shopifyImages)) continue;

            // Find or create gallery for this color
            $gallerySlug = \Illuminate\Support\Str::slug($colorName)
                ?: 'color-' . substr(md5($colorName), 0, 8);

            $gallery = ProductGallery::firstOrCreate(
                ['pd_id' => $product->pd_id, 'pg_slug' => $gallerySlug],
                ['pg_name' => $colorName, 'pg_position' => 0]
            );
            $totalGalleries++;

            // Delete existing images for this gallery (fresh scrape)
            ProductMedia::where('pd_id', $product->pd_id)
                ->where('pg_id', $gallery->pg_id)
                ->whereIn('pm_type', ['image', 'video'])
                ->delete();

            // Download + store each image
            $position = 1;
            $dir = "products/{$product->pd_handle}/{$gallerySlug}";
            foreach ($shopifyImages as $img) {
                $srcUrl = $img['src'] ?? '';
                if (! $srcUrl) continue;

                // Download image
                $contents = @file_get_contents($srcUrl, false, stream_context_create([
                    'http' => ['timeout' => 10, 'header' => "User-Agent: Mozilla/5.0\r\n"],
                ]));
                if (! $contents) continue;

                $ext      = pathinfo(parse_url($srcUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                $filename = sprintf('%s/%03d.%s', $dir, $position, $ext);
                Storage::disk('public')->put($filename, $contents);

                $localUrl = Storage::disk('public')->url($filename);

                ProductMedia::create([
                    'pd_id'       => $product->pd_id,
                    'pg_id'       => $gallery->pg_id,
                    'pm_src'      => $localUrl,
                    'pm_type'     => 'image',
                    'pm_position' => $position,
                    'pm_alt'      => $colorName,
                ]);

                $position++;
                $totalImages++;
            }
        }

        return ['galleries' => $totalGalleries, 'images' => $totalImages];
    }

    protected function handleRecordCreation(array $data): Model
    {
        $images = $data['gallery_images'] ?? [];
        unset($data['gallery_images']);

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
            // Delete items that were removed from the repeater
            ProductMedia::where('pg_id', $record->pg_id)->whereNotIn('pm_id', $keptIds)->delete();
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
            ->description(fn (): HtmlString => $this->scrapeProgress ? $this->scrapeProgressHtml() : new HtmlString(''))
            ->headerActions([
                CreateAction::make()->label('+ New gallery')->modalWidth('2xl'),
            ])
            ->recordActions([
                EditAction::make()->modalWidth('2xl'),

                // Per-color scrape — runs 1 color only → no timeout
                \Filament\Actions\Action::make('scrape_one')
                    ->label('Scrape')
                    ->icon(Heroicon::ArrowDownTray)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading(fn (ProductGallery $record) => "Scrape '{$record->pg_name}'")
                    ->action(function (ProductGallery $record) {
                        try {
                            $product = $this->getOwnerRecord();
                            $product->loadMissing('variants');
                            $count = $this->scrapeOneGallery($product, $record);
                            Notification::make()->title("Scraped {$count} images for '{$record->pg_name}'")->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Scrape failed: ' . $e->getMessage())->danger()->send();
                        }
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
