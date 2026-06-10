<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Collection card shape — consumed by iStudio LOBPage (mac-families equivalent).
 *
 * Eager loads required: products.productLevelMedia, products.variants
 */
class ProductCollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cheapestProduct = $this->products
            ->where('pd_status', 'active')
            ->sortBy(fn ($p) => $p->variants->where('pv_available', true)->min('price') ?? PHP_INT_MAX)
            ->first();

        $startingPrice = $cheapestProduct
            ? (float) ($cheapestProduct->variants->where('pv_available', true)->min('price') ?? 0)
            : 0;

        $image = $cheapestProduct?->productLevelMedia
            ->where('pm_type', 'image')
            ->sortBy('pm_position')
            ->first();

        $priceStr = $startingPrice > 0
            ? 'เริ่มต้น ฿' . number_format($startingPrice, 0)
            : '';

        return [
            'id'          => $this->pcol_handle,
            'name'        => $this->pcol_title,
            'slug'        => $this->pcol_handle,
            'description' => $priceStr,
            'imageSrc'    => $image?->pm_src ?? '',
            'imageAlt'    => $this->pcol_title,
            'detailHref'  => "/collections/{$this->pcol_handle}",
            'buyHref'     => "/collections/{$this->pcol_handle}",
        ];
    }
}
