<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lob_config', function (Blueprint $table) {
            // URL slug used in /pages/view-all-{slug} — can differ from lc_lob
            // e.g. lc_lob="AirPods", lc_url_slug="music"
            $table->string('lc_url_slug', 100)->nullable()->unique()->after('lc_lob');
        });
    }

    public function down(): void
    {
        Schema::table('lob_config', function (Blueprint $table) {
            $table->dropColumn('lc_url_slug');
        });
    }
};
