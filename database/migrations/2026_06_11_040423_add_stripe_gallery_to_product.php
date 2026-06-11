<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product', function (Blueprint $table) {
            // Override which color gallery to use for FamilyStripe chip thumbnail
            // NULL = auto (cheapest variant's gallery)
            $table->unsignedBigInteger('pd_stripe_gallery_id')->nullable()->after('pdp_content');
            $table->foreign('pd_stripe_gallery_id')
                ->references('pg_id')
                ->on('product_gallery')
                ->nullOnDelete(); // if gallery deleted → revert to auto
        });
    }

    public function down(): void
    {
        Schema::table('product', function (Blueprint $table) {
            $table->dropForeign(['pd_stripe_gallery_id']);
            $table->dropColumn('pd_stripe_gallery_id');
        });
    }
};
