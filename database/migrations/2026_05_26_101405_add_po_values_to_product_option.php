<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_option', function (Blueprint $table) {
            $table->json('po_values')->nullable()->after('po_name');
        });
    }

    public function down(): void
    {
        Schema::table('product_option', function (Blueprint $table) {
            $table->dropColumn('po_values');
        });
    }
};
