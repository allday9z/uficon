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
        Schema::create('badge_preset', function (Blueprint $table) {
            $table->id('bp_id');
            $table->string('bp_text', 100)->unique();          // "NEW", "SALE", "ใหม่" etc.
            $table->string('bp_hex_color', 20)->default('#BF4800'); // CSS color
            $table->string('bp_purpose', 500)->nullable();     // Usage/purpose description
            $table->unsignedSmallInteger('bp_sort_order')->default(0);
            $table->boolean('bp_is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badge_preset');
    }
};
