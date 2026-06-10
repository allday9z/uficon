<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * LOB display group shape — consumed by iStudio LOBPage FamilyStripe + product rows.
 *
 * $this = LobDisplayCollection model instance.
 * Products are resolved on-the-fly (no eager load — call with ->withStartingPrice()).
 */
class LobDisplayCollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Cheapest available product in this sub_lob group
        $cheapest = Product::query()
            ->where('pd_lob', $this->ldc_lob)
            ->where('pd_sub_lob', $this->ldc_sub_lob)
            ->where('pd_status', 'active')
            ->with(['productLevelMedia', 'variants'])
            ->get()
            ->sortBy(fn ($p) => $p->variants->where('pv_available', true)->min('price') ?? PHP_INT_MAX)
            ->first();

        $startingPrice = $cheapest
            ? (float) ($cheapest->variants->where('pv_available', true)->min('price') ?? 0)
            : 0;

        // Hero image: explicit field → cheapest product image fallback
        $heroImage = $this->ldc_hero_image;
        if (! $heroImage && $cheapest) {
            $heroImage = $cheapest->productLevelMedia
                ->where('pm_type', 'image')
                ->sortBy('pm_position')
                ->first()?->pm_src ?? '';
        }

        $priceLabel = $startingPrice > 0
            ? 'เริ่มต้น ฿' . number_format($startingPrice, 0)
            : '';

        return [
            // Identity
            'id'          => $this->ldc_slug,
            'slug'        => $this->ldc_slug,
            'lob'         => $this->ldc_lob,
            'subLob'      => $this->ldc_sub_lob,

            // Display
            'name'        => $this->ldc_title ?? $this->ldc_sub_lob,
            'badge'       => $this->ldc_badge,
            'tagline'     => $this->ldc_tagline,
            'description' => $this->ldc_description,
            'priceLabel'  => $priceLabel,

            // Images
            'imageSrc'    => $heroImage ?? '',
            'imageAlt'    => $this->ldc_title ?? $this->ldc_sub_lob,
            'stripeImage' => $this->ldc_stripe_image ?? $heroImage ?? '',

            // Links
            'detailHref'     => "/collections/{$this->ldc_slug}",
            'buyHref'        => "/collections/{$this->ldc_slug}",
            'heroDetailHref' => $this->ldc_hero_detail_href ?? "/collections/{$this->ldc_slug}",
            'heroBuyHref'    => $this->ldc_hero_buy_href ?? "/collections/{$this->ldc_slug}",

            // Flags
            'isFeatured'  => (bool) $this->ldc_is_featured,
            'sortOrder'   => $this->ldc_sort_order,
        ];
    }
}
