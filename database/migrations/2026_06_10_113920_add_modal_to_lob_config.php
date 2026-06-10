<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lob_config', function (Blueprint $table) {
            // 'compare' = open compare modal | 'url' = navigate | null = no action
            $table->string('lc_banner_action', 50)->default('compare')->after('lc_header_image_mobile');
        });
    }

    public function down(): void
    {
        Schema::table('lob_config', function (Blueprint $table) {
            $table->dropColumn('lc_banner_action');
        });
    }
};
