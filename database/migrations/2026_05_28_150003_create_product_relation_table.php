<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_relation', function (Blueprint $table) {
            $table->bigIncrements('pr_id');
            $table->unsignedBigInteger('pd_id');
            $table->unsignedBigInteger('related_pd_id');
            $table->string('pr_type', 30)->default('accessory'); // accessory | bought_together | upsell
            $table->unsignedSmallInteger('pr_position')->default(0);
            $table->timestamps();

            $table->foreign('pd_id')->references('pd_id')->on('product')->onDelete('cascade');
            $table->foreign('related_pd_id')->references('pd_id')->on('product')->onDelete('cascade');
            $table->unique(['pd_id', 'related_pd_id', 'pr_type'], 'uq_product_relation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_relation');
    }
};
