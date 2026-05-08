<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ============ 1. Extend product table ============
        Schema::table('product', function (Blueprint $table) {
            $table->string('pd_handle', 255)->unique()->nullable()->after('pd_name');
            $table->string('pd_type', 100)->nullable()->after('pd_description');  // Mac, iPhone, iPad...
            $table->decimal('compare_at_price', 12, 2)->nullable()->after('price');
            $table->timestamp('published_at')->nullable()->after('pd_status');
            $table->unsignedBigInteger('shopify_product_id')->nullable()->unique()->after('published_at');
        });

        // ============ 2. Product Option (Color, Storage, etc.) ============
        Schema::create('product_option', function (Blueprint $table) {
            $table->id('po_id');
            $table->unsignedBigInteger('pd_id');
            $table->string('po_name', 100);         // "Color", "Storage", "RAM"
            $table->unsignedTinyInteger('po_position')->default(1); // 1, 2, 3
            $table->timestamps();

            $table->foreign('pd_id')
                ->references('pd_id')
                ->on('product')
                ->cascadeOnDelete();

            $table->index('pd_id', 'idx_po_pd_id');
            $table->unique(['pd_id', 'po_position'], 'uq_po_pd_position');
        });

        // ============ 3. Product Variant (actual SKU) ============
        // MacBook Pro 14" M5 512GB Space Black = 1 variant
        Schema::create('product_variant', function (Blueprint $table) {
            $table->id('pv_id');
            $table->unsignedBigInteger('pd_id');
            $table->string('pv_title', 255);            // "512GB / Space Black"
            $table->string('pv_sku', 100)->nullable();  // "MDE04TH/A"
            $table->string('pv_barcode', 100)->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->string('pv_option1', 255)->nullable(); // "Space Black"
            $table->string('pv_option2', 255)->nullable(); // "512GB"
            $table->string('pv_option3', 255)->nullable();
            $table->decimal('pv_weight', 8, 3)->nullable(); // kg
            $table->boolean('requires_shipping')->default(true);
            $table->boolean('taxable')->default(true);
            $table->boolean('pv_available')->default(true);
            $table->unsignedBigInteger('shopify_variant_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('pd_id')
                ->references('pd_id')
                ->on('product')
                ->cascadeOnDelete();

            $table->index('pd_id', 'idx_pv_pd_id');
            $table->index('pv_sku', 'idx_pv_sku');
            $table->index('pv_available');
        });

        // ============ 4. Drop old inventory FK on pd_id, re-point to pv_id ============
        Schema::table('inventory', function (Blueprint $table) {
            $table->dropForeign(['pd_id']);
            $table->dropUnique('uq_inventory_pd_st');  // unique constraint → dropUnique
            $table->dropIndex('idx_inventory_pd_st');
            $table->dropIndex('idx_inventory_pd_id');

            $table->renameColumn('pd_id', 'pv_id');
        });

        Schema::table('inventory', function (Blueprint $table) {
            $table->foreign('pv_id')
                ->references('pv_id')
                ->on('product_variant')
                ->cascadeOnDelete();

            $table->unique(['pv_id', 'st_id'], 'uq_inventory_pv_st');
            $table->index(['pv_id', 'st_id'], 'idx_inventory_pv_st');
            $table->index('pv_id', 'idx_inventory_pv_id');
        });

        // ============ 5. Product Media (images + videos) ============
        Schema::create('product_media', function (Blueprint $table) {
            $table->id('pm_id');
            $table->unsignedBigInteger('pd_id');
            $table->unsignedBigInteger('pv_id')->nullable(); // variant-specific image
            $table->string('pm_src', 1000);
            $table->string('pm_type', 20)->default('image'); // image | video
            $table->unsignedSmallInteger('pm_position')->default(1);
            $table->string('pm_alt', 255)->nullable();
            $table->unsignedSmallInteger('pm_width')->nullable();
            $table->unsignedSmallInteger('pm_height')->nullable();
            $table->decimal('pm_aspect_ratio', 6, 3)->nullable();
            $table->unsignedBigInteger('shopify_media_id')->nullable();
            $table->timestamps();

            $table->foreign('pd_id')
                ->references('pd_id')
                ->on('product')
                ->cascadeOnDelete();

            $table->foreign('pv_id')
                ->references('pv_id')
                ->on('product_variant')
                ->nullOnDelete();

            $table->index('pd_id', 'idx_pm_pd_id');
            $table->index(['pd_id', 'pm_position'], 'idx_pm_pd_position');
        });

        // ============ 6. Product Tag ============
        Schema::create('product_tag', function (Blueprint $table) {
            $table->id('ptag_id');
            $table->unsignedBigInteger('pd_id');
            $table->string('ptag_name', 100);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('pd_id')
                ->references('pd_id')
                ->on('product')
                ->cascadeOnDelete();

            $table->unique(['pd_id', 'ptag_name'], 'uq_ptag_pd_name');
            $table->index('pd_id', 'idx_ptag_pd_id');
            $table->index('ptag_name', 'idx_ptag_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_tag');
        Schema::dropIfExists('product_media');

        // Revert inventory FK back to pd_id
        Schema::table('inventory', function (Blueprint $table) {
            $table->dropForeign(['pv_id']);
            $table->dropIndex('uq_inventory_pv_st');
            $table->dropIndex('idx_inventory_pv_st');
            $table->dropIndex('idx_inventory_pv_id');
            $table->renameColumn('pv_id', 'pd_id');
        });

        Schema::table('inventory', function (Blueprint $table) {
            $table->foreign('pd_id')
                ->references('pd_id')
                ->on('product')
                ->cascadeOnDelete();
        });

        Schema::dropIfExists('product_variant');
        Schema::dropIfExists('product_option');

        Schema::table('product', function (Blueprint $table) {
            $table->dropColumn(['pd_handle', 'pd_type', 'compare_at_price', 'published_at', 'shopify_product_id']);
        });
    }
};
