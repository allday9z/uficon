<?php
namespace Database\Seeders;
use App\Models\BadgePreset;
use Illuminate\Database\Seeder;

class BadgePresetSeeder extends Seeder
{
    public function run(): void
    {
        $presets = [
            ['bp_text'=>'NEW',                 'bp_hex_color'=>'#BF4800','bp_purpose'=>'New Product Introduction — placed above product title in Family Stripe','bp_sort_order'=>1],
            ['bp_text'=>'ใหม่',               'bp_hex_color'=>'#BF4800','bp_purpose'=>'New Product — Thai label for Family Stripe chips','bp_sort_order'=>2],
            ['bp_text'=>'SALE',                'bp_hex_color'=>'#E30000','bp_purpose'=>'Sale / Promotional pricing — accessories and previous-gen products','bp_sort_order'=>3],
            ['bp_text'=>'TRADE-IN',            'bp_hex_color'=>'#0071E3','bp_purpose'=>'Trade-in offer — customer brings old device to exchange','bp_sort_order'=>4],
            ['bp_text'=>'PRE-ORDER',           'bp_hex_color'=>'#006400','bp_purpose'=>'Pre-order — product not yet released, accepting advance orders','bp_sort_order'=>5],
            ['bp_text'=>'FREE GIFT AND OFFER', 'bp_hex_color'=>'#0071E3','bp_purpose'=>'Free gift or bonus offer included with purchase — drives discovery','bp_sort_order'=>6],
            ['bp_text'=>'CARBON NEUTRAL',      'bp_hex_color'=>'#006400','bp_purpose'=>'Carbon neutral certified product (Apple Watch 2030 environmental criteria)','bp_sort_order'=>7],
            ['bp_text'=>'PRIDE EDITION',       'bp_hex_color'=>'#9B4DCA','bp_purpose'=>'Special edition supporting LGBTQ+ — Pride collection only','bp_sort_order'=>8],
        ];

        foreach ($presets as $data) {
            BadgePreset::updateOrCreate(['bp_text' => $data['bp_text']], $data + ['bp_is_active' => true]);
        }
    }
}
