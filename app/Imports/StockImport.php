<?php

namespace App\Imports;

use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\Store;
use Rap2hpoutre\FastExcel\FastExcel;

class StockImport
{
    public array $errors = [];
    public int $updated = 0;
    public int $skipped = 0;

    public function import(string $filePath): void
    {
        (new FastExcel)->import($filePath, function (array $row) {
            try {
                $this->processRow($row);
            } catch (\Exception $e) {
                $sku = $row['pv_sku'] ?? 'unknown';
                $store = $row['store_code'] ?? 'unknown';
                $this->errors[] = "SKU [{$sku}] / Store [{$store}]: " . $e->getMessage();
            }
        });
    }

    private function processRow(array $row): void
    {
        if (empty($row['pv_sku']) || empty($row['store_code'])) {
            return;
        }

        $variant = ProductVariant::where('pv_sku', $row['pv_sku'])->first();
        if (!$variant) {
            $this->skipped++;
            $this->errors[] = "ไม่พบ SKU: {$row['pv_sku']}";
            return;
        }

        $store = Store::where('st_code', $row['store_code'])->first();
        if (!$store) {
            $this->skipped++;
            $this->errors[] = "ไม่พบสาขา: {$row['store_code']}";
            return;
        }

        Inventory::updateOrCreate(
            [
                'pv_id' => $variant->pv_id,
                'st_id' => $store->st_id,
            ],
            [
                'qty_available'   => (int) ($row['qty_available'] ?? 0),
                'qty_reserved'    => (int) ($row['qty_reserved'] ?? 0),
                'qty_damaged'     => (int) ($row['qty_damaged'] ?? 0),
                'last_counted_at' => $row['last_counted_at'] ?? now(),
            ]
        );

        $this->updated++;
    }
}
