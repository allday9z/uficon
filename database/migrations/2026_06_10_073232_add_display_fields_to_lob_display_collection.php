<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lob_display_collection', function (Blueprint $table) {
            // Image shown in LOB product row (right side) — different from hero banner
            $table->string('ldc_image_src', 2000)->nullable()->after('ldc_hero_buy_href');
            // Button label override — default "สั่งซื้อ", use "ดูสินค้า" for accessories
            $table->string('ldc_button_label', 100)->nullable()->after('ldc_image_src');
            // Main href override — for custom/external links (accessories, promo pages)
            $table->string('ldc_href', 500)->nullable()->after('ldc_button_label');
        });
    }

    public function down(): void
    {
        Schema::table('lob_display_collection', function (Blueprint $table) {
            $table->dropColumn(['ldc_image_src', 'ldc_button_label', 'ldc_href']);
        });
    }
};
