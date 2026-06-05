<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product', function (Blueprint $table) {
            $table->json('pd_content_sections')->nullable()->after('pd_features');
            $table->longText('pd_overview')->nullable()->after('pd_content_sections');
        });
    }

    public function down(): void
    {
        Schema::table('product', function (Blueprint $table) {
            $table->dropColumn(['pd_content_sections', 'pd_overview']);
        });
    }
};
