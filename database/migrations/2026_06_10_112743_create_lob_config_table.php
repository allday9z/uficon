<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lob_config', function (Blueprint $table) {
            $table->id('lc_id');
            $table->string('lc_lob', 100)->unique();          // e.g. "Mac", "iPhone"

            // Header banner (shown below FamilyStripe, above hero/product list)
            $table->string('lc_header_image_desktop', 2000)->nullable();
            $table->string('lc_header_image_mobile', 2000)->nullable();
            $table->string('lc_header_text', 500)->nullable();          // "iPhone ใดที่เหมาะกับคุณ?"
            $table->string('lc_header_btn_label', 100)->nullable();     // "เปรียบเทียบทุกรุ่น"
            $table->string('lc_header_btn_href', 500)->nullable();      // "/iphone/compare"
            $table->string('lc_header_link', 500)->nullable();          // whole banner click href

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lob_config');
    }
};
