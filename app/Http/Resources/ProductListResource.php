<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PLP card shape — consumed by iStudio PLPPage and PLPProductRow.
 *
 * Eager loads required: variants, galleries.media
 */
class ProductListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cheapestVariant = $this->variants
            ->where('pv_available', true)
            ->sortBy('price')
            ->first();

        // Deterministic Representative Image: use defaultColor gallery
        // defaultColor = cheapest available variant's gallery (pg_id)
        $defaultGallery = $cheapestVariant?->pg_id
            ? $this->galleries->firstWhere('pg_id', $cheapestVariant->pg_id)
            : $this->galleries->first();

        $firstImage = $defaultGallery?->media
            ->where('pm_type', 'image')
            ->sortBy('pm_position')
            ->first();

        return [
            'id'          => $this->pd_handle,
            'slug'        => $this->pd_handle,
            // defaultVariantHandle: cheapest available variant's pv_handle → PDP entry URL
            'defaultVariantHandle' => $cheapestVariant?->pv_handle ?? $this->pd_handle,
            'name'        => $this->pd_primary_title ?? $this->pd_name,
            'description' => $this->pd_description ?? '',
            'badge'       => $this->pd_badge,
            'badgeColor'  => '#bf4800',
            'imageSrc'    => $firstImage?->pm_src ?? '',
            'imageAlt'    => $firstImage?->pm_alt ?? ($this->pd_name ?? ''),
            'detailHref'  => '/products/' . ($cheapestVariant?->pv_handle ?? $this->pd_handle),
            'buyHref'     => '/products/' . ($cheapestVariant?->pv_handle ?? $this->pd_handle),
            'price'       => [
                'base'     => (float) ($cheapestVariant?->price ?? $this->price ?? 0),
                'currency' => 'THB',
            ],
            'templateType' => $this->pd_template_type ?? 'simple',
            'category'     => $this->collection?->pcol_handle,
            'collection'   => $this->collection?->pcol_handle,
        ];
    }
}
