<?php

namespace App\Imports;

use App\Models\Brand;
use App\Models\Store;
use Rap2hpoutre\FastExcel\FastExcel;

class StoreImport
{
    public array $errors = [];
    public int $created = 0;
    public int $skipped = 0;

    public function import(string $filePath): void
    {
        (new FastExcel)->import($filePath, function (array $row) {
            try {
                $this->processRow($row);
            } catch (\Exception $e) {
                $name = $row['st_name'] ?? 'unknown';
                $this->errors[] = "Store [{$name}]: " . $e->getMessage();
            }
        });
    }

    private function processRow(array $row): void
    {
        if (empty($row['st_name']) || empty($row['brand_code'])) {
            $this->skipped++;
            return;
        }

        $brand = Brand::where('brand_code', $row['brand_code'])
            ->orWhere('brand_name', $row['brand_code'])
            ->first();

        if (!$brand) {
            $this->skipped++;
            $this->errors[] = "ไม่พบ Brand: {$row['brand_code']}";
            return;
        }

        $phones = [];
        if (!empty($row['st_phone'])) {
            $phones = array_map('trim', explode(',', $row['st_phone']));
        }

        $images = [];
        if (!empty($row['image_url'])) {
            $images = array_filter(array_map('trim', explode(',', $row['image_url'])));
        }

        $services = null;
        if (!empty($row['st_services'])) {
            $decoded = json_decode($row['st_services'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $services = $decoded;
            }
        }

        Store::updateOrCreate(
            ['st_code' => $row['st_code'] ?? null],
            [
                'brand_id'         => $brand->brand_id,
                'st_name'          => $row['st_name'],
                'st_is_active'     => isset($row['st_is_active']) ? (bool) $row['st_is_active'] : true,
                'st_phone'         => $phones,
                'st_address'       => $row['st_address'] ?? null,
                'st_full_address'  => $row['st_full_address'] ?? null,
                'st_hours'         => $row['st_hours'] ?? null,
                'st_services'      => $services,
                'latitude'         => $row['latitude'] ?? null,
                'longitude'        => $row['longitude'] ?? null,
                'google_map_url'   => $row['google_map_url'] ?? null,
                'st_contact_links' => $row['st_contact_links'] ?? null,
                'images'           => $images ?: null,
            ]
        );

        $this->created++;
    }
}
