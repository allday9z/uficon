<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_inbox', function (Blueprint $table) {
            $table->string('pib_image', 500)->nullable()->after('pib_text');
        });
    }

    public function down(): void
    {
        Schema::table('product_inbox', function (Blueprint $table) {
            $table->dropColumn('pib_image');
        });
    }
};
