<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PLP card shape — consumed by iStudio PLPPage and PLPProductRow.
 *
 * Eager loads required: productLevelMedia
 */
class ProductListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $firstImage = $this->productLevelMedia
            ->where('pm_type', 'image')
            ->sortBy('pm_position')
            ->first();

        $cheapestVariant = $this->variants
            ->where('pv_available', true)
            ->sortBy('price')
            ->first();

        return [
            'id'          => $this->pd_handle,
            'slug'        => $this->pd_handle,
            'name'        => $this->pd_primary_title ?? $this->pd_name,
            'description' => $this->pd_description ?? '',
            'badge'       => $this->pd_badge,
            'badgeColor'  => '#bf4800',
            'imageSrc'    => $firstImage?->pm_src ?? '',
            'imageAlt'    => $firstImage?->pm_alt ?? ($this->pd_name ?? ''),
            'detailHref'  => "/products/{$this->pd_handle}",
            'buyHref'     => "/products/{$this->pd_handle}",
            'price'       => [
                'base'     => (float) ($cheapestVariant?->price ?? $this->price ?? 0),
                'currency' => 'THB',
            ],
            'category'    => $this->collection?->pcol_handle,
            'collection'  => $this->collection?->pcol_handle,
        ];
    }
}
