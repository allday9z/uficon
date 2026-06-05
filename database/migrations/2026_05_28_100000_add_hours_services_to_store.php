<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store', function (Blueprint $table) {
            $table->string('st_hours')->nullable()->after('st_full_address');
            $table->json('st_services')->nullable()->after('st_hours');
        });
    }

    public function down(): void
    {
        Schema::table('store', function (Blueprint $table) {
            $table->dropColumn(['st_hours', 'st_services']);
        });
    }
};
