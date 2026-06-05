<?php

namespace Database\Seeders;

use App\Models\ImportColumnMap;
use App\Models\ImportProfile;
use Illuminate\Database\Seeder;

class ImportProfileSeeder extends Seeder
{
    public function run(): void
    {
        $profile = ImportProfile::updateOrCreate(
            ['ip_slug' => 'caas'],
            [
                'ip_name'        => 'Apple CaaS Flat File',
                'ip_sheet_name'  => 'Product',
                'ip_header_row'  => 1,
                'ip_description' => 'Apple Commerce-as-a-Service flat file. 1 row = 1 variant (MPN level). Groups by Primary display title.',
            ]
        );

        // Wipe existing maps for clean re-seed
        ImportColumnMap::where('ip_id', $profile->ip_id)->delete();

        $maps = [
            // ── Product-level fields (create_only for status/price) ────────
            ['model' => 'Product', 'field' => 'pd_lob',             'index' => 0,  'header' => 'LOB',                          'mode' => 'always',       'required' => false, 'cast' => null,    'pos' => 10],
            ['model' => 'Product', 'field' => 'pd_sub_lob',         'index' => 1,  'header' => 'Sub LOB',                      'mode' => 'always',       'required' => false, 'cast' => null,    'pos' => 11],
            ['model' => 'Product', 'field' => 'pd_primary_title',   'index' => 6,  'header' => 'Primary display title',        'mode' => 'always',       'required' => true,  'cast' => null,    'pos' => 20],
            ['model' => 'Product', 'field' => 'pd_secondary_title', 'index' => 7,  'header' => 'Secondary display title',      'mode' => 'always',       'required' => false, 'cast' => null,    'pos' => 21],
            ['model' => 'Product', 'field' => 'pd_type',            'index' => 8,  'header' => 'Product type',                 'mode' => 'always',       'required' => false, 'cast' => null,    'pos' => 22],
            ['model' => 'Product', 'field' => 'pd_description',     'index' => 10, 'header' => 'Description',                  'mode' => 'always',       'required' => false, 'cast' => null,    'pos' => 30],
            ['model' => 'Product', 'field' => 'pd_features',        'index' => 11, 'header' => 'Features',                     'mode' => 'always',       'required' => false, 'cast' => null,    'pos' => 31],
            ['model' => 'Product', 'field' => 'pd_base_name',       'index' => 12, 'header' => 'Base product name',            'mode' => 'always',       'required' => false, 'cast' => null,    'pos' => 32],
            ['model' => 'Product', 'field' => 'pd_warranty_parts',  'index' => 25, 'header' => "Manufacturer's warranty parts",'mode' => 'always',       'required' => false, 'cast' => null,    'pos' => 40],
            ['model' => 'Product', 'field' => 'pd_warranty_labor',  'index' => 26, 'header' => "Manufacturer's warranty labor",'mode' => 'always',       'required' => false, 'cast' => null,    'pos' => 41],
            ['model' => 'Product', 'field' => 'pd_status',          'index' => null,'header' => null,                          'mode' => 'create_only',  'required' => false, 'cast' => null,    'pos' => 50, 'default' => 'active'],
            ['model' => 'Product', 'field' => 'price',              'index' => null,'header' => 'Price',                       'mode' => 'create_only',  'required' => false, 'cast' => 'float', 'pos' => 51, 'default' => '0'],

            // ── ProductVariant-level fields ────────────────────────────────
            ['model' => 'ProductVariant', 'field' => 'pv_mpn',         'index' => 2,  'header' => 'Mpn',              'mode' => 'always',      'required' => true,  'cast' => null,    'pos' => 100],
            ['model' => 'ProductVariant', 'field' => 'pv_barcode',     'index' => 3,  'header' => 'Barcode',          'mode' => 'always',      'required' => false, 'cast' => 'string','pos' => 101],
            ['model' => 'ProductVariant', 'field' => 'pv_handle',      'index' => 4,  'header' => 'Handle',           'mode' => 'always',      'required' => false, 'cast' => null,    'pos' => 102],
            ['model' => 'ProductVariant', 'field' => 'pv_caas_title',  'index' => 5,  'header' => 'Title',            'mode' => 'always',      'required' => false, 'cast' => null,    'pos' => 103],
            ['model' => 'ProductVariant', 'field' => 'pv_option1',     'index' => 14, 'header' => 'Option 1 Value',   'mode' => 'always',      'required' => false, 'cast' => null,    'pos' => 110],
            ['model' => 'ProductVariant', 'field' => 'pv_option2',     'index' => 15, 'header' => 'Option 2 Value',   'mode' => 'always',      'required' => false, 'cast' => null,    'pos' => 111],
            ['model' => 'ProductVariant', 'field' => 'pv_option3',     'index' => 16, 'header' => 'Option 3 Value',   'mode' => 'always',      'required' => false, 'cast' => null,    'pos' => 112],
            ['model' => 'ProductVariant', 'field' => 'pv_option4',     'index' => 17, 'header' => 'Option 4 Value',   'mode' => 'always',      'required' => false, 'cast' => null,    'pos' => 113],
            ['model' => 'ProductVariant', 'field' => 'pv_option5',     'index' => 18, 'header' => 'Option 5 Value',   'mode' => 'always',      'required' => false, 'cast' => null,    'pos' => 114],
            ['model' => 'ProductVariant', 'field' => 'pv_option6',     'index' => 19, 'header' => 'Option 6 Value',   'mode' => 'always',      'required' => false, 'cast' => null,    'pos' => 115],
            ['model' => 'ProductVariant', 'field' => 'pv_option7',     'index' => 20, 'header' => 'Option 7 Value',   'mode' => 'always',      'required' => false, 'cast' => null,    'pos' => 116],
            ['model' => 'ProductVariant', 'field' => 'pv_color_swatch','index' => 27, 'header' => 'Color swatch image file',   'mode' => 'always', 'required' => false, 'cast' => null, 'pos' => 120],
            ['model' => 'ProductVariant', 'field' => 'pv_band_swatch', 'index' => 28, 'header' => 'Band color swatch image file','mode' => 'always','required' => false,'cast' => null, 'pos' => 121],
            ['model' => 'ProductVariant', 'field' => 'price',          'index' => null,'header' => 'Variant Price',    'mode' => 'create_only', 'required' => false, 'cast' => 'float', 'pos' => 130, 'default' => '0'],

            // ── Product media images (img1-15) ─────────────────────────────
            ['model' => 'ProductMedia', 'field' => 'img1',  'index' => 29, 'header' => 'Product image 1',  'mode' => 'always', 'required' => false, 'cast' => null, 'pos' => 200],
            ['model' => 'ProductMedia', 'field' => 'img2',  'index' => 30, 'header' => 'Product image 2',  'mode' => 'always', 'required' => false, 'cast' => null, 'pos' => 201],
            ['model' => 'ProductMedia', 'field' => 'img3',  'index' => 31, 'header' => 'Product image 3',  'mode' => 'always', 'required' => false, 'cast' => null, 'pos' => 202],
            ['model' => 'ProductMedia', 'field' => 'img4',  'index' => 32, 'header' => 'Product image 4',  'mode' => 'always', 'required' => false, 'cast' => null, 'pos' => 203],
            ['model' => 'ProductMedia', 'field' => 'img5',  'index' => 33, 'header' => 'Product image 5',  'mode' => 'always', 'required' => false, 'cast' => null, 'pos' => 204],
            ['model' => 'ProductMedia', 'field' => 'img6',  'index' => 34, 'header' => 'Product image 6',  'mode' => 'always', 'required' => false, 'cast' => null, 'pos' => 205],
            ['model' => 'ProductMedia', 'field' => 'img7',  'index' => 35, 'header' => 'Product image 7',  'mode' => 'always', 'required' => false, 'cast' => null, 'pos' => 206],
            ['model' => 'ProductMedia', 'field' => 'img8',  'index' => 36, 'header' => 'Product image 8',  'mode' => 'always', 'required' => false, 'cast' => null, 'pos' => 207],
            ['model' => 'ProductMedia', 'field' => 'img9',  'index' => 37, 'header' => 'Product image 9',  'mode' => 'always', 'required' => false, 'cast' => null, 'pos' => 208],
            ['model' => 'ProductMedia', 'field' => 'img10', 'index' => 38, 'header' => 'Product image 10', 'mode' => 'always', 'required' => false, 'cast' => null, 'pos' => 209],
            ['model' => 'ProductMedia', 'field' => 'img11', 'index' => 39, 'header' => 'Product image 11', 'mode' => 'always', 'required' => false, 'cast' => null, 'pos' => 210],
            ['model' => 'ProductMedia', 'field' => 'img12', 'index' => 40, 'header' => 'Product image 12', 'mode' => 'always', 'required' => false, 'cast' => null, 'pos' => 211],
            ['model' => 'ProductMedia', 'field' => 'img13', 'index' => 41, 'header' => 'Product image 13', 'mode' => 'always', 'required' => false, 'cast' => null, 'pos' => 212],
            ['model' => 'ProductMedia', 'field' => 'img14', 'index' => 42, 'header' => 'Product image 14', 'mode' => 'always', 'required' => false, 'cast' => null, 'pos' => 213],
            ['model' => 'ProductMedia', 'field' => 'img15', 'index' => 43, 'header' => 'Product image 15', 'mode' => 'always', 'required' => false, 'cast' => null, 'pos' => 214],

            // ── Videos ─────────────────────────────────────────────────────
            ['model' => 'ProductMedia', 'field' => 'video1', 'index' => 44, 'header' => 'Video asset 1', 'mode' => 'always', 'required' => false, 'cast' => null, 'pos' => 220],
            ['model' => 'ProductMedia', 'field' => 'video2', 'index' => 45, 'header' => 'Video asset 2', 'mode' => 'always', 'required' => false, 'cast' => null, 'pos' => 221],
            ['model' => 'ProductMedia', 'field' => 'video3', 'index' => 46, 'header' => 'Video asset 3', 'mode' => 'always', 'required' => false, 'cast' => null, 'pos' => 222],

            // ── Collection (grouping/lookup only) ──────────────────────────
            ['model' => 'ProductCollection', 'field' => 'pcol_handle', 'index' => 13, 'header' => 'Base product collection', 'mode' => 'always', 'required' => true, 'cast' => null, 'pos' => 300],

            // ── Brand ──────────────────────────────────────────────────────
            ['model' => 'Brand', 'field' => 'brand_name', 'index' => 9, 'header' => 'Vendor', 'mode' => 'always', 'required' => false, 'cast' => null, 'pos' => 400],

            // ── Inbox (in the box items) ────────────────────────────────────
            ['model' => 'ProductInbox', 'field' => 'inbox1', 'index' => 21, 'header' => 'In the box 1', 'mode' => 'always', 'required' => false, 'cast' => null, 'pos' => 500],
            ['model' => 'ProductInbox', 'field' => 'inbox2', 'index' => 22, 'header' => 'In the box 2', 'mode' => 'always', 'required' => false, 'cast' => null, 'pos' => 501],
            ['model' => 'ProductInbox', 'field' => 'inbox3', 'index' => 23, 'header' => 'In the box 3', 'mode' => 'always', 'required' => false, 'cast' => null, 'pos' => 502],
            ['model' => 'ProductInbox', 'field' => 'inbox4', 'index' => 24, 'header' => 'In the box 4', 'mode' => 'always', 'required' => false, 'cast' => null, 'pos' => 503],
        ];

        foreach ($maps as $map) {
            ImportColumnMap::create([
                'ip_id'              => $profile->ip_id,
                'icm_source_header'  => $map['header'],
                'icm_source_index'   => $map['index'],
                'icm_target_model'   => $map['model'],
                'icm_target_field'   => $map['field'],
                'icm_default_value'  => $map['default'] ?? null,
                'icm_required'       => $map['required'],
                'icm_update_mode'    => $map['mode'],
                'icm_cast'           => $map['cast'],
                'icm_position'       => $map['pos'],
            ]);
        }

        $this->command->info('✅ CaaS import profile seeded: ' . count($maps) . ' column maps');
    }
}
