<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product', function (Blueprint $table) {
            $table->string('pd_template_type', 30)->default('normal')->after('pd_type');
        });

        Schema::table('product_option', function (Blueprint $table) {
            $table->string('po_display', 30)->default('button')->after('po_name');
        });
    }

    public function down(): void
    {
        Schema::table('product', function (Blueprint $table) {
            $table->dropColumn('pd_template_type');
        });

        Schema::table('product_option', function (Blueprint $table) {
            $table->dropColumn('po_display');
        });
    }
};
