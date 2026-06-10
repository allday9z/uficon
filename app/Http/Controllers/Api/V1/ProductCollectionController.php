<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCollectionResource;
use App\Http\Resources\ProductListResource;
use App\Models\ProductCollection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

class ProductCollectionController extends Controller
{
    /** GET /api/v1/collections — LOB family cards */
    public function index(): AnonymousResourceCollection
    {
        $collections = ProductCollection::with([
            'products' => fn ($q) => $q->where('pd_status', 'active')
                ->with(['productLevelMedia', 'variants']),
        ])->get();

        return ProductCollectionResource::collection($collections);
    }

    /** GET /api/v1/collections/{slug}/products — PLP product cards */
    public function products(string $slug): AnonymousResourceCollection|JsonResponse
    {
        $collection = ProductCollection::where('pcol_handle', $slug)->first();

        if (! $collection) {
            return response()->json(['message' => 'Collection not found'], 404);
        }

        $products = $collection->products()
            ->where('pd_status', 'active')
            ->with(['productLevelMedia', 'variants', 'collection'])
            ->get();

        return ProductListResource::collection($products);
    }
}
