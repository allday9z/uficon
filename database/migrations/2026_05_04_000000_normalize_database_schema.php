<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ============ 1. Brand (Parent) ============
        Schema::create('brand', function (Blueprint $table) {
            $table->id('brand_id');
            $table->string('brand_name')->unique();
            $table->string('brand_code', 50)->unique();
            $table->string('brand_icon', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ============ 2. Product Category (Parent) ============
        Schema::create('product_category', function (Blueprint $table) {
            $table->id('pc_id');
            $table->string('pc_name', 255)->unique();
            $table->text('pc_description')->nullable();
            $table->string('pc_status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('pc_status');
        });

        // ============ 3. Product ⭐ (Main Product Table) ============
        Schema::create('product', function (Blueprint $table) {
            $table->id('pd_id');
            $table->unsignedBigInteger('brand_id');
            $table->string('pd_name', 255);
            $table->text('pd_description')->nullable();
            $table->string('pd_sku', 100)->unique();
            $table->decimal('price', 12, 2);
            $table->string('pd_status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('brand_id')
                ->references('brand_id')
                ->on('brand')
                ->cascadeOnDelete();

            // Indexes
            $table->index('brand_id', 'idx_product_brand_id');
            $table->index('pd_status');
            $table->index('pd_sku');
            // Partial index for active products
            $table->index('pd_id', 'idx_product_active');
        });

        // ============ 4. Product Category Map (N:M) ============
        Schema::create('product_category_map', function (Blueprint $table) {
            $table->id('pcm_id');
            $table->unsignedBigInteger('pd_id');
            $table->unsignedBigInteger('pc_id');
            $table->timestamp('created_at')->useCurrent();

            // Foreign Keys
            $table->foreign('pd_id')
                ->references('pd_id')
                ->on('product')
                ->cascadeOnDelete();
            
            $table->foreign('pc_id')
                ->references('pc_id')
                ->on('product_category')
                ->cascadeOnDelete();

            // Unique constraint to prevent duplicates
            $table->unique(['pd_id', 'pc_id']);

            // Indexes
            $table->index('pd_id', 'idx_pcm_pd_id');
            $table->index('pc_id', 'idx_pcm_pc_id');
        });

        // ============ 5. Store ⭐ (Branch/Store Master) ============
        Schema::create('store', function (Blueprint $table) {
            $table->id('st_id');
            $table->unsignedBigInteger('brand_id');
            $table->string('st_name', 255);
            $table->text('st_address')->nullable();
            $table->text('st_full_address')->nullable();
            $table->string('st_code', 50)->unique();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('google_map_url', 500)->nullable();
            $table->json('st_phone')->nullable();
            $table->json('st_contact_links')->nullable();
            $table->json('images')->nullable();
            $table->boolean('st_is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('brand_id')
                ->references('brand_id')
                ->on('brand')
                ->cascadeOnDelete();

            // Indexes
            $table->index('brand_id', 'idx_store_brand_id');
            $table->index('st_code');
            $table->index('st_is_active');
        });

        // ============ 6. Inventory ⭐ KEY TABLE: Stock Per Store ============
        Schema::create('inventory', function (Blueprint $table) {
            $table->id('inv_id');
            $table->unsignedBigInteger('pd_id');
            $table->unsignedBigInteger('st_id');
            $table->integer('qty_available')->default(0);
            $table->integer('qty_reserved')->default(0);
            $table->integer('qty_damaged')->default(0);
            $table->timestamp('last_counted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('pd_id')
                ->references('pd_id')
                ->on('product')
                ->cascadeOnDelete();
            
            $table->foreign('st_id')
                ->references('st_id')
                ->on('store')
                ->cascadeOnDelete();

            // Unique constraint: one inventory record per product+store
            $table->unique(['pd_id', 'st_id'], 'uq_inventory_pd_st');

            // Critical Performance Indexes
            $table->index(['pd_id', 'st_id'], 'idx_inventory_pd_st');
            $table->index('pd_id', 'idx_inventory_pd_id');
            $table->index('st_id', 'idx_inventory_st_id');
            $table->index('qty_available');

            // Check constraints (MySQL 8.0+, PostgreSQL)
            // For MySQL: add check during laravel application logic
            // For PostgreSQL: these work directly
        });

        // ============ 7. Order ============
        Schema::create('order', function (Blueprint $table) {
            $table->id('ord_id');
            $table->unsignedBigInteger('st_id');
            $table->string('ord_customer_name', 255);
            $table->decimal('ord_total_amount', 12, 2);
            $table->string('ord_status', 20)->default('pending');
            $table->date('ord_date');
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('st_id')
                ->references('st_id')
                ->on('store')
                ->restrictOnDelete(); // Don't delete store with pending orders

            // Indexes
            $table->index('st_id', 'idx_order_st_id');
            $table->index('ord_status');
            $table->index('ord_date');
        });

        // ============ 8. Order Item (Order-Product Join) ============
        Schema::create('order_item', function (Blueprint $table) {
            $table->id('oi_id');
            $table->unsignedBigInteger('ord_id');
            $table->unsignedBigInteger('pd_id');
            $table->integer('oi_quantity');
            $table->decimal('oi_price', 12, 2);
            $table->timestamps();

            // Foreign Keys
            $table->foreign('ord_id')
                ->references('ord_id')
                ->on('order')
                ->cascadeOnDelete();
            
            $table->foreign('pd_id')
                ->references('pd_id')
                ->on('product')
                ->restrictOnDelete();

            // Indexes
            $table->index('ord_id', 'idx_oi_ord_id');
            $table->index('pd_id', 'idx_oi_pd_id');
        });

        // ============ API Tokens & Logs (Existing - Keep as is) ============
        // api_token, api_log tables already exist in separate migrations
    }

    public function down(): void
    {
        // Drop in reverse order of dependencies
        Schema::dropIfExists('order_item');
        Schema::dropIfExists('order');
        Schema::dropIfExists('inventory');
        Schema::dropIfExists('store');
        Schema::dropIfExists('product_category_map');
        Schema::dropIfExists('product');
        Schema::dropIfExists('product_category');
        Schema::dropIfExists('brand');
    }
};
