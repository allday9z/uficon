<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lob_display_collection', function (Blueprint $table) {
            $table->date('ldc_sale_date')->nullable()->after('ldc_sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('lob_display_collection', function (Blueprint $table) {
            $table->dropColumn('ldc_sale_date');
        });
    }
};
