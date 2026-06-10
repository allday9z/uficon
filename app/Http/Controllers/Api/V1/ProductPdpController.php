<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductPdpResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductPdpController extends Controller
{
    public function show(string $handle): ProductPdpResource|JsonResponse
    {
        $product = Product::with(['productLevelMedia', 'inbox', 'variants', 'options', 'galleries'])
            ->where('pd_handle', $handle)
            ->where('pd_status', 'active')
            ->firstOrFail();

        return new ProductPdpResource($product);
    }
}
