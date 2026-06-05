<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variant', function (Blueprint $table) {
            $table->boolean('is_pickup_available')->default(true)->after('pv_available');
            $table->string('delivery_lead_time', 100)->nullable()->after('is_pickup_available');
        });
    }

    public function down(): void
    {
        Schema::table('product_variant', function (Blueprint $table) {
            $table->dropColumn(['is_pickup_available', 'delivery_lead_time']);
        });
    }
};
