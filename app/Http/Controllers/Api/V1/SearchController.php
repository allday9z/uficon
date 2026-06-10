<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = trim($request->get('q', ''));
        $limit = min((int) $request->get('limit', 8), 20);

        if (mb_strlen($query) < 2) {
            return response()->json(['suggestions' => [], 'products' => []]);
        }

        // Suggestions: unique pd_primary_title + pd_sub_lob matching query
        $nameSuggestions = Product::where('pd_status', 'active')
            ->where(fn ($q) =>
                $q->where('pd_primary_title', 'ilike', "%{$query}%")
                  ->orWhere('pd_name', 'ilike', "%{$query}%")
            )
            ->distinct()
            ->orderBy('pd_primary_title')
            ->limit(5)
            ->pluck('pd_primary_title')
            ->filter()
            ->values();

        $subLobSuggestions = Product::where('pd_status', 'active')
            ->where('pd_sub_lob', 'ilike', "%{$query}%")
            ->distinct()
            ->orderBy('pd_sub_lob')
            ->limit(3)
            ->pluck('pd_sub_lob')
            ->filter()
            ->values();

        $suggestions = $subLobSuggestions->merge($nameSuggestions)->unique()->take(6)->values();

        // Products: matching products with image + cheapest variant price
        $products = Product::where('pd_status', 'active')
            ->where(fn ($q) =>
                $q->where('pd_primary_title', 'ilike', "%{$query}%")
                  ->orWhere('pd_name', 'ilike', "%{$query}%")
                  ->orWhere('pd_sub_lob', 'ilike', "%{$query}%")
                  ->orWhere('pd_lob', 'ilike', "%{$query}%")
            )
            ->with(['variants', 'galleries.media'])
            ->orderBy('pd_primary_title')
            ->limit($limit)
            ->get()
            ->map(function (Product $p) {
                $cheapestVariant = $p->variants->where('pv_available', true)->sortBy('price')->first();
                $price = (float) ($cheapestVariant?->price ?? 0);

                // Deterministic image: defaultColor gallery → first image
                $defaultGallery = $cheapestVariant?->pg_id
                    ? $p->galleries->firstWhere('pg_id', $cheapestVariant->pg_id)
                    : $p->galleries->first();
                $imageSrc = $defaultGallery?->media->where('pm_type', 'image')->sortBy('pm_position')->first()?->pm_src
                    ?? $defaultGallery?->media->where('pm_type', 'swatch')->first()?->pm_src
                    ?? '';

                return [
                    'id'       => $p->pd_handle,
                    'name'     => $p->pd_primary_title ?? $p->pd_name,
                    'vendor'   => 'Apple',
                    'price'    => $price,
                    'currency' => 'THB',
                    'imageSrc' => $imageSrc,
                    'href'     => "/products/{$p->pd_handle}",
                ];
            })
            ->values();

        return response()->json([
            'query'       => $query,
            'suggestions' => $suggestions,
            'products'    => $products,
        ]);
    }
}
