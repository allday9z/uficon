<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductPdpResource;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ProductPdpController extends Controller
{
    public function show(string $handle): ProductPdpResource|JsonResponse
    {
        $eagerLoads = ['productLevelMedia', 'inbox', 'variants', 'options', 'galleries'];

        // Try variant handle (pv_handle) first — primary PDP entry point
        $variant = ProductVariant::where('pv_handle', $handle)
            ->with('product')
            ->first();

        if ($variant?->product) {
            $product = Product::with($eagerLoads)
                ->find($variant->product->pd_id);

            if (! $product || $product->pd_status !== 'active') {
                return response()->json(['message' => 'Product not found'], 404);
            }

            return (new ProductPdpResource($product))
                ->additional(['selectedVariant' => $this->resolveSelectedVariant($variant)]);
        }

        // Fallback: try product handle (pd_handle) for direct product access
        $product = Product::with($eagerLoads)
            ->where('pd_handle', $handle)
            ->where('pd_status', 'active')
            ->first();

        if ($product) {
            // Pre-select cheapest available variant by default
            $defaultVariant = $product->variants
                ->where('pv_available', true)
                ->sortBy('price')
                ->first();

            return (new ProductPdpResource($product))
                ->additional(['selectedVariant' => $this->resolveSelectedVariant($defaultVariant)]);
        }

        return response()->json(['message' => 'Product not found'], 404);
    }

    private function resolveSelectedVariant(?ProductVariant $variant): ?array
    {
        if (! $variant) return null;

        return [
            'handle'   => $variant->pv_handle,
            'sku'      => $variant->pv_sku,
            'color'    => $variant->pv_option1,
            'colorId'  => $variant->pv_option1
                ? (Str::slug($variant->pv_option1) ?: 'color-' . substr(md5($variant->pv_option1), 0, 8))
                : null,
            'option2'  => $variant->pv_option2,
            'option3'  => $variant->pv_option3,
            'price'    => (float) ($variant->price ?? 0),
            'galleryId' => $variant->pg_id,
        ];
    }
}
