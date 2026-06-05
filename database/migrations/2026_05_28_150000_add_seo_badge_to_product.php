<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product', function (Blueprint $table) {
            $table->string('pd_meta_title', 255)->nullable()->after('pd_handle');
            $table->text('pd_meta_desc')->nullable()->after('pd_meta_title');
            $table->string('pd_meta_image', 500)->nullable()->after('pd_meta_desc');
            $table->string('pd_badge', 50)->nullable()->after('pd_status');
        });
    }

    public function down(): void
    {
        Schema::table('product', function (Blueprint $table) {
            $table->dropColumn(['pd_meta_title', 'pd_meta_desc', 'pd_meta_image', 'pd_badge']);
        });
    }
};
