<?php

namespace Database\Seeders;

use App\Models\OptionName;
use App\Models\ProductType;
use Illuminate\Database\Seeder;

class LookupSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Mac',         'sort_order' => 1],
            ['name' => 'iPhone',      'sort_order' => 2],
            ['name' => 'iPad',        'sort_order' => 3],
            ['name' => 'Apple Watch', 'sort_order' => 4],
            ['name' => 'AirPods',     'sort_order' => 5],
            ['name' => 'Apple TV',    'sort_order' => 6],
            ['name' => 'Accessories', 'sort_order' => 7],
        ];

        foreach ($types as $type) {
            ProductType::firstOrCreate(['name' => $type['name']], $type);
        }

        $options = [
            ['name' => 'Color',    'sort_order' => 1],
            ['name' => 'Storage',  'sort_order' => 2],
            ['name' => 'RAM',      'sort_order' => 3],
            ['name' => 'CPU',      'sort_order' => 4],
            ['name' => 'Size',     'sort_order' => 5],
            ['name' => 'Style',    'sort_order' => 6],
            ['name' => 'Material', 'sort_order' => 7],
        ];

        foreach ($options as $option) {
            OptionName::firstOrCreate(['name' => $option['name']], $option);
        }
    }
}
