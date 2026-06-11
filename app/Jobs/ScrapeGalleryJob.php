<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductGallery;
use App\Models\ProductMedia;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ScrapeGalleryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout    = 300; // 5 minutes
    public int $tries      = 1;
    public string $cacheKey;

    /** Fallback sources — tried in order */
    private const SOURCES = [
        'https://www.istudio.store/products/{handle}.json',
        'https://www.istudiobyspvi.com/products/{handle}.json',
    ];

    public function __construct(
        public readonly int  $productId,
        public readonly ?int $galleryId = null,
    ) {
        $this->cacheKey = "scrape_gallery_{$productId}";
    }

    public function handle(): void
    {
        $product = Product::with('variants')->find($this->productId);
        if (! $product) {
            $this->setProgress(['status' => 'failed', 'error' => 'Product not found']);
            return;
        }

        // ── Single-gallery mode ──────────────────────────────────────────
        if ($this->galleryId !== null) {
            $gallery = ProductGallery::find($this->galleryId);
            if (! $gallery) {
                $this->setProgress(['status' => 'failed', 'error' => 'Gallery not found']);
                return;
            }
            $variant = $product->variants->first(fn ($v) => $v->pv_option1 === $gallery->pg_name)
                ?? $product->variants->whereNotNull('pv_handle')->first();
            if (! $variant) {
                $this->setProgress(['status' => 'failed', 'error' => 'No variant found for ' . $gallery->pg_name]);
                return;
            }

            $this->setProgress(['status' => 'processing', 'total' => 1, 'done' => 0, 'images' => 0, 'current' => $gallery->pg_name, 'errors' => []]);
            $count = $this->scrapeIntoGallery($product, $gallery, $variant);
            $this->setProgress(['status' => 'done', 'total' => 1, 'done' => 1, 'images' => $count, 'errors' => []]);
            return;
        }

        // ── All-colors mode ──────────────────────────────────────────────
        $variants  = $product->variants;
        $colors    = $variants->whereNotNull('pv_option1')->unique('pv_option1')->values();
        $total     = $colors->count();
        $done      = 0;
        $images    = 0;
        $errors    = [];

        $this->setProgress(['status' => 'processing', 'total' => $total, 'done' => 0, 'images' => 0, 'current' => '']);

        foreach ($colors as $variant) {
            $colorName = $variant->pv_option1;
            $this->setProgress(['status' => 'processing', 'total' => $total, 'done' => $done, 'images' => $images, 'current' => $colorName]);

            try {
                $gallerySlug = Str::slug($colorName) ?: 'color-' . substr(md5($colorName), 0, 8);
                $gallery = ProductGallery::firstOrCreate(
                    ['pd_id' => $product->pd_id, 'pg_slug' => $gallerySlug],
                    ['pg_name' => $colorName, 'pg_position' => 0]
                );
                $images += $this->scrapeIntoGallery($product, $gallery, $variant);
            } catch (\Throwable $e) {
                $errors[] = "{$colorName}: " . $e->getMessage();
            }

            $done++;
            $this->setProgress(['status' => 'processing', 'total' => $total, 'done' => $done, 'images' => $images, 'current' => $colorName, 'errors' => $errors]);
        }

        $this->setProgress(['status' => 'done', 'total' => $total, 'done' => $done, 'images' => $images, 'errors' => $errors]);
    }

    private function scrapeIntoGallery(Product $product, ProductGallery $gallery, $variant): int
    {
        $shopifyImages = $this->fetchImages($variant->pv_handle);
        if (empty($shopifyImages)) return 0;

        ProductMedia::where('pd_id', $product->pd_id)
            ->where('pg_id', $gallery->pg_id)
            ->whereIn('pm_type', ['image', 'video'])
            ->delete();

        $dir      = "products/{$product->pd_handle}/{$gallery->pg_slug}";
        $ctx      = $this->httpContext();
        $position = 1;

        foreach ($shopifyImages as $img) {
            $srcUrl = $img['src'] ?? '';
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
                'pm_alt'      => $gallery->pg_name,
            ]);
            $position++;
        }

        return $position - 1;
    }

    private function fetchImages(string $handle): array
    {
        $ctx = $this->httpContext();

        foreach (self::SOURCES as $tpl) {
            $url  = str_replace('{handle}', $handle, $tpl);
            $json = @file_get_contents($url, false, $ctx);
            if (! $json) continue;

            $data   = json_decode($json, true);
            $images = $data['product']['images'] ?? [];
            if (! empty($images)) return $images;
        }

        return [];
    }

    private function httpContext()
    {
        return stream_context_create(['http' => [
            'timeout' => 15,
            'header'  => "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36\r\n",
        ]]);
    }

    private function setProgress(array $data): void
    {
        Cache::put($this->cacheKey, $data, now()->addMinutes(10));
    }

    public function failed(\Throwable $e): void
    {
        $this->setProgress(['status' => 'failed', 'error' => $e->getMessage()]);
    }
}
