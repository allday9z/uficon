<?php

namespace App\Imports;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;
use Rap2hpoutre\FastExcel\FastExcel;

class ProductImport
{
    public array $errors = [];
    public int $created = 0;
    public int $updated = 0;

    public function import(string $filePath): void
    {
        (new FastExcel)->import($filePath, function (array $row) {
            try {
                $this->processRow($row);
            } catch (\Exception $e) {
                $sku = $row['pv_sku'] ?? 'unknown';
                $this->errors[] = "SKU [{$sku}]: " . $e->getMessage();
            }
        });
    }

    private function processRow(array $row): void
    {
        if (empty($row['pd_name']) || empty($row['pv_sku'])) {
            return;
        }

        $brand = Brand::where('brand_code', $row['brand_code'] ?? '')
            ->orWhere('brand_name', $row['brand_code'] ?? '')
            ->first();

        $handle = Str::slug($row['pd_handle'] ?? $row['pd_name']);

        $existing = Product::where('pd_handle', $handle)->first();

        $product = Product::updateOrCreate(
            ['pd_handle' => $handle],
            [
                'brand_id'           => $brand?->brand_id,
                'pd_name'            => $row['pd_name'],
                'pd_handle'          => $handle,
                'pd_description'     => $row['pd_description'] ?? null,
                'pd_type'            => $row['pd_type'] ?? null,
                'pd_status'          => $row['pd_status'] ?? 'active',
                'published_at'       => $row['published_at'] ?? now(),
                'shopify_product_id' => $row['shopify_product_id'] ?? null,
            ]
        );

        ProductVariant::updateOrCreate(
            ['pv_sku' => $row['pv_sku']],
            [
                'pd_id'              => $product->pd_id,
                'pv_title'           => $row['pv_title'] ?? $row['pd_name'],
                'pv_barcode'         => $row['pv_barcode'] ?? null,
                'price'              => $row['price'] ?? 0,
                'compare_at_price'   => $row['compare_at_price'] ?? null,
                'pv_option1'         => $row['pv_option1'] ?? null,
                'pv_option2'         => $row['pv_option2'] ?? null,
                'pv_option3'         => $row['pv_option3'] ?? null,
                'pv_weight'          => $row['pv_weight'] ?? null,
                'requires_shipping'  => filter_var($row['requires_shipping'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'pv_available'       => filter_var($row['pv_available'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'shopify_variant_id' => $row['shopify_variant_id'] ?? null,
            ]
        );

        $existing ? $this->updated++ : $this->created++;
    }
}
