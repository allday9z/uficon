<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LobDisplayCollectionResource;
use App\Http\Resources\ProductListResource;
use App\Models\LobDisplayCollection;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class ProductCollectionController extends Controller
{
    /**
     * GET /api/v1/lob/{lob}/collections
     *
     * Returns display groups for a LOB (e.g. "mac").
     * Matches ldc_lob case-insensitively.
     * Falls back to auto-generating groups from pd_sub_lob when no lob_display_collection rows exist.
     */
    public function lobCollections(string $lob): AnonymousResourceCollection|JsonResponse
    {
        $lobLabel = $this->normalizeLob($lob);

        $configured = LobDisplayCollection::where('ldc_lob', $lobLabel)
            ->where('ldc_is_active', true)
            ->orderBy('ldc_sort_order')
            ->orderBy('ldc_title')
            ->get();

        if ($configured->isNotEmpty()) {
            return LobDisplayCollectionResource::collection($configured);
        }

        // Auto-generate from product pd_sub_lob when no manual config exists
        $subLobs = Product::query()
            ->where('pd_lob', $lobLabel)
            ->where('pd_status', 'active')
            ->select('pd_sub_lob')
            ->distinct()
            ->orderBy('pd_sub_lob')
            ->pluck('pd_sub_lob')
            ->filter();

        if ($subLobs->isEmpty()) {
            return response()->json(['message' => "No collections found for LOB: {$lob}"], 404);
        }

        $autoGroups = $subLobs->map(function (string $subLob) use ($lobLabel) {
            $model = new LobDisplayCollection([
                'ldc_lob'      => $lobLabel,
                'ldc_sub_lob'  => $subLob,
                'ldc_slug'     => Str::slug($subLob),
                'ldc_title'    => $subLob,
                'ldc_is_active'   => true,
                'ldc_is_featured' => false,
                'ldc_sort_order'  => 0,
            ]);
            $model->exists = false;
            return $model;
        });

        return LobDisplayCollectionResource::collection($autoGroups);
    }

    /**
     * GET /api/v1/collections/{slug}
     *
     * Single collection metadata for PLPPage heading/tagline.
     */
    public function show(string $slug): LobDisplayCollectionResource|JsonResponse
    {
        $ldc = LobDisplayCollection::where('ldc_slug', $slug)
            ->where('ldc_is_active', true)
            ->first();

        if ($ldc) {
            return new LobDisplayCollectionResource($ldc);
        }

        // Auto-generate from first matching product sub_lob
        $subLob = Product::query()
            ->where('pd_status', 'active')
            ->whereRaw("LOWER(REPLACE(pd_sub_lob, ' ', '-')) = ?", [strtolower($slug)])
            ->value('pd_sub_lob');

        if (! $subLob) {
            return response()->json(['message' => "Collection not found: {$slug}"], 404);
        }

        $lob = Product::where('pd_sub_lob', $subLob)->value('pd_lob') ?? '';

        $model = new LobDisplayCollection([
            'ldc_lob'      => $lob,
            'ldc_sub_lob'  => $subLob,
            'ldc_slug'     => $slug,
            'ldc_title'    => $subLob,
            'ldc_is_active'   => true,
            'ldc_is_featured' => false,
            'ldc_sort_order'  => 0,
        ]);
        $model->exists = false;

        return new LobDisplayCollectionResource($model);
    }

    /**
     * GET /api/v1/collections/{slug}/products
     *
     * PLP product cards — products grouped by pd_sub_lob matching the slug.
     * Slug is derived as Str::slug(pd_sub_lob): "MacBook Pro" → "macbook-pro".
     */
    public function products(string $slug): AnonymousResourceCollection|JsonResponse
    {
        // Find matching sub_lob via configured record first
        $ldc = LobDisplayCollection::where('ldc_slug', $slug)->first();
        $subLob = $ldc?->ldc_sub_lob;

        // Fallback: match by slugifying pd_sub_lob
        if (! $subLob) {
            $subLob = Product::query()
                ->where('pd_status', 'active')
                ->whereRaw("LOWER(REPLACE(pd_sub_lob, ' ', '-')) = ?", [strtolower($slug)])
                ->value('pd_sub_lob');
        }

        if (! $subLob) {
            return response()->json(['message' => "Collection not found: {$slug}"], 404);
        }

        $products = Product::query()
            ->where('pd_sub_lob', $subLob)
            ->where('pd_status', 'active')
            ->with(['variants', 'galleries.media'])
            ->get();

        return ProductListResource::collection($products);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    /** Normalize URL lob segment to title-case label stored in DB. */
    private function normalizeLob(string $lob): string
    {
        return match (strtolower($lob)) {
            'mac'       => 'Mac',
            'iphone'    => 'iPhone',
            'ipad'      => 'iPad',
            'watch', 'apple-watch' => 'Apple Watch',
            'airpods'   => 'AirPods',
            'tv', 'appletv', 'apple-tv' => 'Apple TV',
            'accessories' => 'Accessories',
            'audio'     => 'Audio',
            'homepod'   => 'HomePod',
            default     => ucfirst($lob),
        };
    }
}
