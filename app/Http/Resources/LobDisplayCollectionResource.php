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
            ->with(['variants', 'galleries.media'])
            ->get()
            ->sortBy(fn ($p) => $p->variants->where('pv_available', true)->min('price') ?? PHP_INT_MAX)
            ->first();

        $startingPrice = $cheapest
            ? (float) ($cheapest->variants->where('pv_available', true)->min('price') ?? 0)
            : 0;

        // Deterministic Representative Image: use defaultColor gallery
        // defaultColor = cheapest available variant's gallery
        $heroImage = $this->ldc_hero_image;
        if (! $heroImage && $cheapest) {
            $cheapestVariant = $cheapest->variants->where('pv_available', true)->sortBy('price')->first();
            $defaultGallery  = $cheapestVariant?->pg_id
                ? $cheapest->galleries->firstWhere('pg_id', $cheapestVariant->pg_id)
                : $cheapest->galleries->first();
            $heroImage = $defaultGallery?->media
                ->where('pm_type', 'image')
                ->sortBy('pm_position')
                ->first()?->pm_src ?? '';
        }

        $priceLabel = $startingPrice > 0
            ? 'เริ่มต้น ฿' . number_format($startingPrice, 0)
            : '';

        $mainHref = $this->ldc_href ?? "/collections/{$this->ldc_slug}";

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
            // ldc_image_src = LOB row image (product photo on right side)
            // ldc_hero_image = hero banner (only shown when isFeatured)
            // falls back to cheapest product's first image
            'imageSrc'    => $this->ldc_image_src ?? $heroImage ?? '',
            'imageAlt'    => $this->ldc_title ?? $this->ldc_sub_lob,
            'stripeImage' => $this->ldc_stripe_image ?? $this->ldc_image_src ?? $heroImage ?? '',
            'heroImage'   => $this->ldc_hero_image ?? '',

            // Links
            'detailHref'     => $mainHref,
            'buyHref'        => $mainHref,
            'buttonLabel'    => $this->ldc_button_label ?? 'สั่งซื้อ',
            'heroDetailHref' => $this->ldc_hero_detail_href ?? $mainHref,
            'heroBuyHref'    => $this->ldc_hero_buy_href ?? $mainHref,

            // Flags
            'isFeatured'  => (bool) $this->ldc_is_featured,
            'sortOrder'   => $this->ldc_sort_order,
        ];
    }
}
