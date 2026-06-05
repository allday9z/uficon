<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_addon_map', function (Blueprint $table) {
            $table->bigIncrements('pam_id');
            $table->unsignedBigInteger('pd_id');
            $table->unsignedBigInteger('addon_pd_id');
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->foreign('pd_id')->references('pd_id')->on('product')->onDelete('cascade');
            $table->foreign('addon_pd_id')->references('pd_id')->on('product')->onDelete('cascade');
            $table->unique(['pd_id', 'addon_pd_id'], 'uq_product_addon_map');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_addon_map');
    }
};
