<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CaaS (Commerce as a Service) Flat File — 100% field coverage
 * Apple CaaS format: 47 columns (Products sheet) + 19 columns (Smart Collection sheet)
 *
 * New tables:
 *   product_collection  — Smart Collection (LOB grouping + option labels/values)
 *   product_inbox       — "In the box" items (up to 4 per product)
 *
 * Extended tables:
 *   product             — LOB, Sub LOB, display titles, features, base name, warranty, collection FK
 *   product_variant     — MPN, per-variant handle, CaaS title, option4-7, swatch images
 */
return new class extends Migration
{
    public function up(): void
    {
        // ════════════════════════════════════════════════════════════════
        // 1. product_collection  (Smart Collection sheet)
        // ════════════════════════════════════════════════════════════════
        Schema::create('product_collection', function (Blueprint $table) {
            $table->id('pcol_id');
            $table->string('pcol_title', 500)->nullable();                 // Title
            $table->string('pcol_handle', 255)->unique();                  // Handle (Base product collection)
            $table->string('pcol_rule_column', 255)->nullable();           // Rule: Product Column
            $table->string('pcol_rule_relation', 50)->nullable();          // Rule: Relation
            $table->text('pcol_rule_condition')->nullable();               // Rule: Condition
            $table->json('pcol_option_labels')->nullable();                // {"1":"Case Finish","2":"Band Color",...}
            $table->json('pcol_option_values')->nullable();                // {"1":"Silver|Gold|...","2":"Black|Denim|..."}
            $table->timestamps();
        });

        // ════════════════════════════════════════════════════════════════
        // 2. product_inbox  ("In the box" items)
        // ════════════════════════════════════════════════════════════════
        Schema::create('product_inbox', function (Blueprint $table) {
            $table->id('pib_id');
            $table->unsignedBigInteger('pd_id');
            $table->string('pib_text', 500);
            $table->unsignedTinyInteger('pib_position')->default(1);      // 1-4

            $table->foreign('pd_id')
                ->references('pd_id')
                ->on('product')
                ->cascadeOnDelete();

            $table->index('pd_id', 'idx_pib_pd_id');
        });

        // ════════════════════════════════════════════════════════════════
        // 3. Extend product table — CaaS product-level fields
        // ════════════════════════════════════════════════════════════════
        Schema::table('product', function (Blueprint $table) {
            // Make pd_sku nullable — CaaS has no product-level SKU (variant-level only)
            $table->string('pd_sku', 100)->nullable()->change();

            // Collection FK (Smart Collection / Base product collection)
            $table->unsignedBigInteger('pcol_id')->nullable()->after('brand_id');

            // CaaS: Primary display title / Secondary display title
            $table->string('pd_primary_title', 500)->nullable()->after('pd_name');
            $table->string('pd_secondary_title', 500)->nullable()->after('pd_primary_title');

            // CaaS: Features (separate from Description — rich HTML)
            $table->longText('pd_features')->nullable()->after('pd_description');

            // CaaS: Base product name (grouping label, e.g. "Apple Watch Series 10 Sport Band")
            $table->string('pd_base_name', 255)->nullable()->after('pd_features');

            // CaaS: LOB / Sub LOB
            $table->string('pd_lob', 100)->nullable()->after('pd_handle');
            $table->string('pd_sub_lob', 150)->nullable()->after('pd_lob');

            // CaaS: Manufacturer's warranty
            $table->text('pd_warranty_parts')->nullable()->after('pd_type');
            $table->text('pd_warranty_labor')->nullable()->after('pd_warranty_parts');

            $table->foreign('pcol_id')
                ->references('pcol_id')
                ->on('product_collection')
                ->nullOnDelete();

            $table->index('pcol_id', 'idx_product_pcol_id');
            $table->index('pd_lob', 'idx_product_lob');
            $table->index('pd_base_name', 'idx_product_base_name');
        });

        // ════════════════════════════════════════════════════════════════
        // 4. Extend product_variant table — CaaS variant-level fields
        // ════════════════════════════════════════════════════════════════
        Schema::table('product_variant', function (Blueprint $table) {
            // CaaS: Mpn (Manufacturer Part Number — distinct from internal SKU)
            $table->string('pv_mpn', 100)->nullable()->after('pv_sku');

            // CaaS: per-variant URL handle (e.g. "apple-watch-series-10-sport-band-mwwf3qf-a")
            $table->string('pv_handle', 255)->nullable()->after('pv_barcode');

            // CaaS: Full CaaS variant title (e.g. "Apple Watch Series 10 GPS 42mm Jet Black...")
            $table->string('pv_caas_title', 500)->nullable()->after('pv_title');

            // CaaS: Option 4-7 (current schema has option1-3 only; Apple Watch uses up to 6)
            $table->string('pv_option4', 255)->nullable()->after('pv_option3');
            $table->string('pv_option5', 255)->nullable()->after('pv_option4');
            $table->string('pv_option6', 255)->nullable()->after('pv_option5');
            $table->string('pv_option7', 255)->nullable()->after('pv_option6');

            // CaaS: Color swatch + Band color swatch image URLs (per variant)
            $table->string('pv_color_swatch', 1000)->nullable()->after('pv_option7');
            $table->string('pv_band_swatch', 1000)->nullable()->after('pv_color_swatch');

            $table->index('pv_mpn', 'idx_pv_mpn');
            $table->index('pv_handle', 'idx_pv_handle');
        });
    }

    public function down(): void
    {
        Schema::table('product_variant', function (Blueprint $table) {
            $table->dropIndex('idx_pv_mpn');
            $table->dropIndex('idx_pv_handle');
            $table->dropColumn([
                'pv_mpn', 'pv_handle', 'pv_caas_title',
                'pv_option4', 'pv_option5', 'pv_option6', 'pv_option7',
                'pv_color_swatch', 'pv_band_swatch',
            ]);
        });

        Schema::table('product', function (Blueprint $table) {
            $table->dropForeign(['pcol_id']);
            $table->dropIndex('idx_product_pcol_id');
            $table->dropIndex('idx_product_lob');
            $table->dropIndex('idx_product_base_name');
            $table->dropColumn([
                'pcol_id', 'pd_primary_title', 'pd_secondary_title',
                'pd_features', 'pd_base_name', 'pd_lob', 'pd_sub_lob',
                'pd_warranty_parts', 'pd_warranty_labor',
            ]);
            $table->string('pd_sku', 100)->nullable(false)->change();
        });

        Schema::dropIfExists('product_inbox');
        Schema::dropIfExists('product_collection');
    }
};
