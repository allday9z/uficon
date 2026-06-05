<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_gallery', function (Blueprint $table) {
            $table->id('pg_id');
            $table->unsignedBigInteger('pd_id');
            $table->string('pg_name', 100);
            $table->string('pg_slug', 100);
            $table->smallInteger('pg_position')->default(0);
            $table->timestamps();

            $table->foreign('pd_id')->references('pd_id')->on('product')->cascadeOnDelete();
            $table->unique(['pd_id', 'pg_slug'], 'uq_gallery_pd_slug');
            $table->index('pd_id');
        });

        Schema::table('product_media', function (Blueprint $table) {
            $table->unsignedBigInteger('pg_id')->nullable()->after('pv_id');
            $table->foreign('pg_id')->references('pg_id')->on('product_gallery')->nullOnDelete();
            $table->index('pg_id');
        });

        Schema::table('product_variant', function (Blueprint $table) {
            $table->unsignedBigInteger('pg_id')->nullable()->after('pv_id');
            $table->foreign('pg_id')->references('pg_id')->on('product_gallery')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_variant', function (Blueprint $table) {
            $table->dropForeign(['pg_id']);
            $table->dropColumn('pg_id');
        });

        Schema::table('product_media', function (Blueprint $table) {
            $table->dropForeign(['pg_id']);
            $table->dropIndex(['pg_id']);
            $table->dropColumn('pg_id');
        });

        Schema::dropIfExists('product_gallery');
    }
};
