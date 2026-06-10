<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lob_display_collection', function (Blueprint $table) {
            $table->id('ldc_id');

            // Mapping to CaaS product fields
            $table->string('ldc_lob', 100);                       // matches pd_lob  e.g. "Mac"
            $table->string('ldc_sub_lob', 200);                   // matches pd_sub_lob e.g. "MacBook Pro"
            $table->string('ldc_slug', 200)->unique();             // URL slug e.g. "macbook-pro"

            // Display fields
            $table->string('ldc_title', 500)->nullable();          // PLP h1 heading
            $table->string('ldc_badge', 100)->nullable();          // "ใหม่" / "Sale"
            $table->text('ldc_tagline')->nullable();               // hero h1 / PLP sub-heading
            $table->text('ldc_description')->nullable();           // hero description body
            $table->string('ldc_hero_image', 2000)->nullable();    // LOB hero banner image URL
            $table->string('ldc_hero_detail_href', 500)->nullable();
            $table->string('ldc_hero_buy_href', 500)->nullable();
            $table->string('ldc_stripe_image', 2000)->nullable();  // FamilyStripe chip thumbnail URL

            $table->unsignedTinyInteger('ldc_sort_order')->default(0);
            $table->boolean('ldc_is_featured')->default(false);    // show as LOB hero (max 1 per ldc_lob)
            $table->boolean('ldc_is_active')->default(true);

            $table->timestamps();

            $table->index('ldc_lob');
            $table->index(['ldc_lob', 'ldc_is_active', 'ldc_sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lob_display_collection');
    }
};
