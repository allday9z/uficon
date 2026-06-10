<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\LobDisplayCollection;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\ProductGallery;
use App\Models\ProductInbox;
use App\Models\ProductMedia;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Import Apple CaaS (Commerce as a Service) Flat File into the uficon product schema.
 *
 * CaaS format: 1 row = 1 variant (MPN level)
 * Grouping: rows with the same `Base product collection` handle → 1 Product record
 *
 * Usage:
 *   php artisan products:import-caas /path/to/CaaS-Flat-File.xlsx
 *   php artisan products:import-caas /path/to/CaaS-Flat-File.xlsx --dry-run
 *   php artisan products:import-caas /path/to/CaaS-Flat-File.xlsx --fresh  (truncate existing CaaS products first)
 */
class ImportCaasProducts extends Command
{
    protected $signature = 'products:import-caas
                            {file : Absolute path to CaaS Flat File .xlsx}
                            {--dry-run : Parse and report without writing to DB}
                            {--fresh : Delete existing products matching imported collection handles before import}';

    protected $description = 'Import Apple CaaS Flat File (Products + Smart Collection sheets) into uficon product schema';

    public int $createdCount = 0;
    public int $updatedCount = 0;

    // LOBs recognised by the system — mapped to their default button label
    private const KNOWN_LOBS = [
        'Mac'         => 'สั่งซื้อ',
        'iPhone'      => 'สั่งซื้อ',
        'iPad'        => 'สั่งซื้อ',
        'Apple Watch' => 'สั่งซื้อ',
        'AirPods'     => 'สั่งซื้อ',
        'Apple TV'    => 'สั่งซื้อ',
        'Audio'       => 'สั่งซื้อ',
        'HomePod'     => 'สั่งซื้อ',
        // Accessories sub-lobs link to a browse page, not a direct buy
        'Accessories' => 'ดูสินค้า',
    ];

    // ── Column indices (0-based) ─────────────────────────────────────────
    private const PRODUCTS_COLS = [
        'lob'             => 0,   // LOB
        'sub_lob'         => 1,   // Sub LOB
        'mpn'             => 2,   // Mpn
        'barcode'         => 3,   // Barcode
        'handle'          => 4,   // Handle (per-variant)
        'title'           => 5,   // Title (per-variant full title)
        'primary_title'   => 6,   // Primary display title
        'secondary_title' => 7,   // Secondary display title
        'product_type'    => 8,   // Product type
        'vendor'          => 9,   // Vendor (Brand)
        'description'     => 10,  // Description
        'features'        => 11,  // Features
        'base_name'       => 12,  // Base product name
        'collection'      => 13,  // Base product collection (handle)
        'opt1'            => 14,  // Option 1 Value
        'opt2'            => 15,  // Option 2 Value
        'opt3'            => 16,  // Option 3 Value
        'opt4'            => 17,  // Option 4 Value
        'opt5'            => 18,  // Option 5 Value
        'opt6'            => 19,  // Option 6 Value
        'opt7'            => 20,  // Option 7 Value
        'inbox1'          => 21,  // In the box 1
        'inbox2'          => 22,  // In the box 2
        'inbox3'          => 23,  // In the box 3
        'inbox4'          => 24,  // In the box 4
        'warranty_parts'  => 25,  // Manufacturer's warranty parts
        'warranty_labor'  => 26,  // Manufacturer's warranty labor
        'color_swatch'    => 27,  // Color swatch image file
        'band_swatch'     => 28,  // Band color swatch image file
        'img1'            => 29,  // Product image 1
        'img2'            => 30,
        'img3'            => 31,
        'img4'            => 32,
        'img5'            => 33,
        'img6'            => 34,
        'img7'            => 35,
        'img8'            => 36,
        'img9'            => 37,
        'img10'           => 38,
        'img11'           => 39,
        'img12'           => 40,
        'img13'           => 41,
        'img14'           => 42,
        'img15'           => 43,  // Product image 15
        'video1'          => 44,  // Video asset 1
        'video2'          => 45,  // Video asset 2
        'video3'          => 46,  // Video asset 3
        'price'           => 47,  // Normal Price (THB)
    ];

    private const COLLECTION_COLS = [
        'title'        => 0,   // Title
        'handle'       => 1,   // Handle
        'rule_col'     => 2,   // Rule: Product Column
        'rule_rel'     => 3,   // Rule: Relation
        'rule_cond'    => 4,   // Rule: Condition
        'opt1_label'   => 5,   // Option 1 Label
        'opt2_label'   => 6,
        'opt3_label'   => 7,
        'opt4_label'   => 8,
        'opt5_label'   => 9,
        'opt6_label'   => 10,
        'opt7_label'   => 11,  // Option 7 Label
        'opt1_values'  => 12,  // Option 1 Values (pipe-separated)
        'opt2_values'  => 13,
        'opt3_values'  => 14,
        'opt4_values'  => 15,
        'opt5_values'  => 16,
        'opt6_values'  => 17,
        'opt7_values'  => 18,  // Option 7 Values
    ];

    public function handle(): int
    {
        $filePath = $this->argument('file');
        $isDryRun = $this->option('dry-run');
        $isFresh  = $this->option('fresh');

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return self::FAILURE;
        }

        $this->info("Reading: {$filePath}");
        if ($isDryRun) {
            $this->warn('[DRY RUN] No changes will be written to DB.');
        }

        [$collections, $productRows] = $this->readSheets($filePath);

        $this->info(sprintf(
            'Parsed: %d collection rows, %d product rows',
            count($collections),
            count($productRows)
        ));

        // Group product rows by Primary display title — each unique title = 1 Product
        $groups = [];
        foreach ($productRows as $row) {
            $key = $this->cell($row, 'primary_title');
            if (! $key) {
                continue;
            }
            $groups[$key][] = $row;
        }

        $this->info(sprintf('Product groups (by primary title): %d', count($groups)));
        foreach ($groups as $primaryTitle => $rows) {
            $this->line("  {$primaryTitle}: {$rows[0][self::PRODUCTS_COLS['lob']]} / {$rows[0][self::PRODUCTS_COLS['sub_lob']]} ({$rows[0][self::PRODUCTS_COLS['product_type']]}) — " . count($rows) . ' variants');
        }

        // ── Validate LOB values ──────────────────────────────────────────
        $unknownLobs = $this->validateLobs($groups);
        if (! empty($unknownLobs)) {
            $this->newLine();
            $this->warn('⚠️  Unknown LOB values detected — ข้อมูลเหล่านี้ยังไม่ถูก map ใน KNOWN_LOBS:');
            foreach ($unknownLobs as $lob => $subLobs) {
                $this->warn("  LOB \"{$lob}\" → sub-LOBs: " . implode(', ', $subLobs));
            }
            $this->warn('  → กรุณาเพิ่ม LOB เหล่านี้ใน KNOWN_LOBS ใน ImportCaasProducts.php ก่อน import');
            $this->warn('  → หรือสร้าง LobDisplayCollection record ด้วยตนเองใน /uf-admin/lob-collections');
            if (! $this->confirm('  ดำเนิน import ต่อแม้จะมี unknown LOBs ไหม?', true)) {
                $this->info('Import ยกเลิก');
                return self::FAILURE;
            }
            $this->newLine();
        }

        if ($isDryRun) {
            $this->info('[DRY RUN] Done — no DB writes.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($collections, $groups, $isFresh) {
            // ── Step 1: Upsert Smart Collections ────────────────────────
            $this->processCollections($collections);

            // ── Step 2: Optionally delete existing products matching these primary titles
            if ($isFresh) {
                $titles = array_keys($groups);
                // Include soft-deleted records in the count + force-delete both active & trashed
                $count = Product::withTrashed()->whereIn('pd_primary_title', $titles)->count();
                Product::withTrashed()->whereIn('pd_primary_title', $titles)->forceDelete();
                $this->warn("--fresh: force-deleted {$count} existing products (active + trashed).");
            } else {
                // Restore any orphaned soft-deleted products that will be re-imported
                $titles = array_keys($groups);
                $restored = Product::onlyTrashed()->whereIn('pd_primary_title', $titles)->restore();
                if ($restored > 0) {
                    $this->warn("Restored {$restored} soft-deleted products before re-import.");
                }
            }

            // ── Step 3: Import products ──────────────────────────────────
            foreach ($groups as $primaryTitle => $rows) {
                $this->importProductGroup($primaryTitle, $rows);
            }

            // ── Step 4: Auto-sync lob_display_collection ─────────────────
            $this->syncLobDisplayCollections($groups);
        });

        $this->info(sprintf(
            '✅ Import complete — สร้าง %d, อัปเดต %d รายการ',
            $this->createdCount,
            $this->updatedCount
        ));

        // Verify: no empty-slug galleries remain (Thai slug rule compliance)
        $emptyCount = ProductGallery::where('pg_slug', '')->orWhereNull('pg_slug')->count();
        if ($emptyCount > 0) {
            $this->warn("⚠️  {$emptyCount} galleries ยังมี empty slug — รัน artisan tinker แล้ว cleanup ด้วย Thai slug fallback rule");
        }

        return self::SUCCESS;
    }

    // ── Sheet reading ────────────────────────────────────────────────────

    private function readSheets(string $filePath): array
    {
        $reader = new Reader();
        $reader->open($filePath);

        $collections = [];
        $productRows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $name = $sheet->getName();
            $isProducts   = str_contains($name, 'Product') && ! str_contains($name, 'Collection');
            $isCollection = str_contains($name, 'Collection');

            $rowIndex = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $cells = array_map(
                    fn ($cell) => $cell->getValue(),
                    $row->getCells()
                );
                $rowIndex++;

                if ($rowIndex === 1) {
                    continue; // skip header row
                }

                // Pad row to expected length to avoid undefined offset
                $cells = array_pad($cells, 50, null);

                if ($isCollection) {
                    $collections[] = $cells;
                } elseif ($isProducts) {
                    $productRows[] = $cells;
                }
            }
        }

        $reader->close();
        return [$collections, $productRows];
    }

    // ── Smart Collection processing ──────────────────────────────────────

    private function processCollections(array $collections): void
    {
        $cols = self::COLLECTION_COLS;

        foreach ($collections as $row) {
            $handle = trim((string) ($row[$cols['handle']] ?? ''));
            if (! $handle) {
                continue;
            }

            $labels = [];
            $values = [];
            for ($i = 1; $i <= 7; $i++) {
                $labelKey  = "opt{$i}_label";
                $valuesKey = "opt{$i}_values";
                $label = trim((string) ($row[$cols[$labelKey]] ?? ''));
                $val   = trim((string) ($row[$cols[$valuesKey]] ?? ''));
                if ($label) {
                    $labels[$i] = $label;
                }
                if ($val) {
                    $values[$i] = $val;
                }
            }

            ProductCollection::updateOrCreate(
                ['pcol_handle' => $handle],
                [
                    'pcol_title'         => trim((string) ($row[$cols['title']] ?? '')),
                    'pcol_rule_column'   => trim((string) ($row[$cols['rule_col']] ?? '')) ?: null,
                    'pcol_rule_relation' => trim((string) ($row[$cols['rule_rel']] ?? '')) ?: null,
                    'pcol_rule_condition'=> trim((string) ($row[$cols['rule_cond']] ?? '')) ?: null,
                    'pcol_option_labels' => $labels ?: null,
                    'pcol_option_values' => $values ?: null,
                ]
            );

            $this->line("  [collection] upserted: {$handle}");
        }
    }

    // ── Product group import ─────────────────────────────────────────────

    private function importProductGroup(string $primaryTitle, array $rows): void
    {
        $firstRow = $rows[0];
        $cols     = self::PRODUCTS_COLS;

        // Brand (Vendor)
        $vendorName = trim((string) ($firstRow[$cols['vendor']] ?? 'Unknown'));
        $brand = Brand::firstOrCreate(
            ['brand_name' => $vendorName],
            ['brand_code' => Str::slug($vendorName)]
        );

        // Collection — resolved from row data (collection handle col 13)
        $collectionHandle = $this->cell($firstRow, 'collection');
        $collection = ProductCollection::firstOrCreate(
            ['pcol_handle' => $collectionHandle],
            ['pcol_title'  => $primaryTitle ?: $collectionHandle]
        );

        // Product name = primary title (grouping key), fallback to base_name / title
        $productName = $primaryTitle
                    ?: $this->cell($firstRow, 'base_name')
                    ?: $this->cell($firstRow, 'title');

        // Use pcol_handle (CaaS Base product collection) as pd_handle — guaranteed unique.
        // Str::slug($primaryTitle) can collide when Thai text strips to the same ASCII
        // e.g. "iPhone Air" and "กรอบกันกระแทกสำหรับ iPhone Air" both → "iphone-air".
        $handle          = $collectionHandle ?: Str::slug($productName);
        $existingProduct = Product::withTrashed()->where('pd_handle', $handle)->first();
        // Also check old slug-based handle to migrate existing data
        if (! $existingProduct && $collectionHandle) {
            $existingProduct = Product::withTrashed()->where('pd_handle', Str::slug($productName))->first();
            if ($existingProduct) {
                $existingProduct->pd_handle = $handle;
                $existingProduct->save();
            }
        }
        $existed         = (bool) $existingProduct;

        $productData = [
            'brand_id'           => $brand->brand_id,
            'pcol_id'            => $collection->pcol_id,
            'pd_name'            => $productName,
            'pd_primary_title'   => $this->cell($firstRow, 'primary_title'),
            'pd_secondary_title' => $this->cell($firstRow, 'secondary_title'),
            'pd_lob'             => $this->cell($firstRow, 'lob'),
            'pd_sub_lob'         => $this->cell($firstRow, 'sub_lob'),
            'pd_description'     => $this->cell($firstRow, 'description'),
            'pd_features'        => $this->cell($firstRow, 'features'),
            'pd_base_name'       => $this->cell($firstRow, 'base_name'),
            'pd_type'            => $this->cell($firstRow, 'product_type'),
            'pd_template_type'   => $this->resolveTemplateType($this->cell($firstRow, 'product_type')),
            'pd_warranty_parts'  => $this->cell($firstRow, 'warranty_parts'),
            'pd_warranty_labor'  => $this->cell($firstRow, 'warranty_labor'),
            'pd_status'          => 'active',
            'published_at'       => now(),
        ];

        if ($existingProduct) {
            if ($existingProduct->trashed()) {
                $existingProduct->restore();
            }
            $existingProduct->fill($productData)->save();
            $product = $existingProduct;
        } else {
            $productData['price'] = 0;
            $product = Product::create(array_merge(['pd_handle' => $handle], $productData));
        }

        $existed ? $this->updatedCount++ : $this->createdCount++;

        // When updating: clear all child records and rebuild from scratch
        if ($existed) {
            $product->options()->delete();
            $product->inbox()->delete();
            $product->media()->delete();
            $product->galleries()->delete();
        }

        // Safety: purge any leftover empty-slug galleries for this product
        // (survived from old imports before Thai slug fix was applied)
        ProductGallery::where('pd_id', $product->pd_id)
            ->where(fn ($q) => $q->where('pg_slug', '')->orWhereNull('pg_slug'))
            ->delete();

        // Option definitions (from collection option labels)
        $optionLabels = $collection->pcol_option_labels ?? [];
        foreach ($optionLabels as $idx => $label) {
            ProductOption::create([
                'pd_id'       => $product->pd_id,
                'po_name'     => $label,
                'po_display'  => $this->resolveOptionDisplay($label),
                'po_position' => (int) $idx,
            ]);
        }

        // In the box items (from first row — shared across product)
        $inboxPosition = 1;
        foreach (['inbox1', 'inbox2', 'inbox3', 'inbox4'] as $key) {
            $text = $this->cell($firstRow, $key);
            if ($text) {
                ProductInbox::create([
                    'pd_id'        => $product->pd_id,
                    'pib_text'     => $text,
                    'pib_position' => $inboxPosition++,
                ]);
            }
        }

        // Generate pd_overview HTML from CaaS product-level fields
        $product->pd_overview = $this->buildOverviewHtml(
            $this->cell($firstRow, 'description'),
            $this->cell($firstRow, 'features'),
            array_filter(array_map(fn ($k) => $this->cell($firstRow, $k), ['inbox1', 'inbox2', 'inbox3', 'inbox4'])),
            $this->cell($firstRow, 'warranty_labor'),
            $this->cell($firstRow, 'warranty_parts')
        );
        $product->save();

        // Variants (1 per CaaS row) — galleries are created here
        foreach ($rows as $row) {
            $this->importVariant($product, $row);
        }

        // Per-color images: take first row of each color → link to that color's gallery
        $seenColors = [];
        $totalMedia = 0;
        foreach ($rows as $row) {
            $colorName = $this->cell($row, 'opt1');
            $colorKey  = $colorName ?? '__no_color__';

            if (isset($seenColors[$colorKey])) {
                continue; // already imported images for this color
            }
            $seenColors[$colorKey] = true;

            $gallery = null;
            if ($colorName) {
                $slug    = $this->gallerySlug($colorName);
                $gallery = ProductGallery::where('pd_id', $product->pd_id)
                    ->where('pg_slug', $slug)
                    ->first();
            }

            $position = 1;
            for ($i = 1; $i <= 15; $i++) {
                $url = $this->cell($row, 'img' . $i);
                if ($url) {
                    ProductMedia::create([
                        'pd_id'       => $product->pd_id,
                        'pg_id'       => $gallery?->pg_id,
                        'pm_src'      => $url,
                        'pm_type'     => 'image',
                        'pm_position' => $position++,
                        'pm_alt'      => $colorName ?? $productName,
                    ]);
                    $totalMedia++;
                }
            }

            foreach (['video1', 'video2', 'video3'] as $key) {
                $url = $this->cell($row, $key);
                if ($url) {
                    ProductMedia::create([
                        'pd_id'       => $product->pd_id,
                        'pg_id'       => $gallery?->pg_id,
                        'pm_src'      => $url,
                        'pm_type'     => 'video',
                        'pm_position' => $position++,
                        'pm_alt'      => null,
                    ]);
                    $totalMedia++;
                }
            }
        }

        $this->info(sprintf(
            '  [product] %s — %d variants, %d colors, %d media, %d inbox items',
            $productName,
            count($rows),
            count($seenColors),
            $totalMedia,
            $inboxPosition - 1
        ));
    }

    // ── Single variant import ────────────────────────────────────────────

    private function importVariant(Product $product, array $row): void
    {
        $cols = self::PRODUCTS_COLS;

        // Build variant title from non-empty option values
        $options = array_filter([
            $this->cell($row, 'opt1'),
            $this->cell($row, 'opt2'),
            $this->cell($row, 'opt3'),
            $this->cell($row, 'opt4'),
            $this->cell($row, 'opt5'),
            $this->cell($row, 'opt6'),
            $this->cell($row, 'opt7'),
        ]);
        $variantTitle = implode(' / ', $options) ?: $this->cell($row, 'title');

        $mpn            = $this->cell($row, 'mpn');
        $variantData    = [
            'pd_id'             => $product->pd_id,
            'pv_title'          => $variantTitle,
            'pv_caas_title'     => $this->cell($row, 'title'),
            'pv_sku'            => $mpn,
            'pv_barcode'        => $this->cell($row, 'barcode') ? (string) $this->cell($row, 'barcode') : null,
            'pv_handle'         => $this->cell($row, 'handle'),
            'pv_option1'        => $this->cell($row, 'opt1'),
            'pv_option2'        => $this->cell($row, 'opt2'),
            'pv_option3'        => $this->cell($row, 'opt3'),
            'pv_option4'        => $this->cell($row, 'opt4'),
            'pv_option5'        => $this->cell($row, 'opt5'),
            'pv_option6'        => $this->cell($row, 'opt6'),
            'pv_option7'        => $this->cell($row, 'opt7'),
            'pv_color_swatch'   => $this->cell($row, 'color_swatch'),
            'pv_band_swatch'    => $this->cell($row, 'band_swatch'),
            'pv_available'      => true,
            'requires_shipping' => true,
            'taxable'           => true,
        ];

        // Auto-create gallery keyed by color (opt1) — one gallery per unique color
        $colorName = $this->cell($row, 'opt1');
        $gallery = null;
        if ($colorName) {
            $gallerySlug = $this->gallerySlug($colorName);
            $gallery = ProductGallery::firstOrCreate(
                ['pd_id' => $product->pd_id, 'pg_slug' => $gallerySlug],
                ['pg_name' => $colorName, 'pg_position' => 0]
            );
            $variantData['pg_id'] = $gallery->pg_id;
        }

        // Set price from CaaS column 47 "Normal Price" — fallback 0 if absent
        $priceRaw = $this->cell($row, 'price');
        $variantData['price'] = $priceRaw !== null ? (float) str_replace(',', '', $priceRaw) : 0;

        $variant = ProductVariant::withTrashed()->where('pv_mpn', $mpn)->first();
        if ($variant) {
            if ($variant->trashed()) {
                $variant->restore();
            }
            $variant->fill($variantData)->save();
        } else {
            $variant = ProductVariant::create(array_merge(['pv_mpn' => $mpn], $variantData));
        }

        // Swatch images as gallery-linked media (not pv_id anymore)
        if ($swatchUrl = $this->cell($row, 'color_swatch')) {
            $swatchExists = ProductMedia::where('pd_id', $product->pd_id)
                ->where('pm_src', $swatchUrl)
                ->where('pm_type', 'swatch')
                ->exists();
            if (! $swatchExists) {
                ProductMedia::create([
                    'pd_id'       => $product->pd_id,
                    'pg_id'       => $gallery?->pg_id,
                    'pm_src'      => $swatchUrl,
                    'pm_type'     => 'swatch',
                    'pm_position' => 1,
                    'pm_alt'      => $colorName,
                ]);
            }
        }

        if ($bandSwatchUrl = $this->cell($row, 'band_swatch')) {
            $bandExists = ProductMedia::where('pd_id', $product->pd_id)
                ->where('pm_src', $bandSwatchUrl)
                ->where('pm_type', 'band_swatch')
                ->exists();
            if (! $bandExists) {
                ProductMedia::create([
                    'pd_id'       => $product->pd_id,
                    'pg_id'       => $gallery?->pg_id,
                    'pm_src'      => $bandSwatchUrl,
                    'pm_type'     => 'band_swatch',
                    'pm_position' => 2,
                    'pm_alt'      => $this->cell($row, 'opt2'),
                ]);
            }
        }
    }

    // ── Helper ───────────────────────────────────────────────────────────

    private function resolveTemplateType(?string $productType): string
    {
        if (! $productType) return 'normal';
        $lower = strtolower($productType);
        $fulldetail = ['mac', 'macbook', 'imac', 'mac pro', 'mac mini', 'mac studio', 'ipad', 'laptop'];
        foreach ($fulldetail as $keyword) {
            if (str_contains($lower, $keyword)) return 'fulldetail';
        }
        return 'normal';
    }

    private function resolveOptionDisplay(string $optionName): string
    {
        $lower = strtolower(trim($optionName));
        return match (true) {
            str_contains($lower, 'band color') || $lower === 'band'   => 'band_swatch',
            str_contains($lower, 'color') || str_contains($lower, 'finish') || str_contains($lower, 'colour') => 'color_swatch',
            str_contains($lower, 'processor') || str_contains($lower, 'chip') || str_contains($lower, 'cpu') => 'dropdown',
            default => 'button',
        };
    }

    private function buildOverviewHtml(
        ?string $description,
        ?string $featuresRaw,
        array   $inboxItems,
        ?string $warrantyLabor,
        ?string $warrantyParts
    ): string {
        $html = '';

        if (filled($description)) {
            $html .= '<section class="overview-description">'
                . '<p>' . e($description) . '</p>'
                . '</section>';
        }

        if (filled($featuresRaw)) {
            $bullets = array_filter(array_map('trim', explode('<br>', $featuresRaw)));
            if (! empty($bullets)) {
                $html .= '<section class="overview-features">'
                    . '<ul>'
                    . implode('', array_map(fn ($b) => '<li>' . $b . '</li>', $bullets))
                    . '</ul>'
                    . '</section>';
            }
        }

        if (! empty($inboxItems)) {
            $html .= '<section class="overview-inbox">'
                . '<ul>'
                . implode('', array_map(fn ($i) => '<li>' . e($i) . '</li>', $inboxItems))
                . '</ul>'
                . '</section>';
        }

        if (filled($warrantyLabor) || filled($warrantyParts)) {
            $html .= '<section class="overview-warranty"><table>';
            if (filled($warrantyLabor)) {
                $html .= '<tr><td>Manufacturer\'s Warranty - Labor</td><td>' . e($warrantyLabor) . '</td></tr>';
            }
            if (filled($warrantyParts)) {
                $html .= '<tr><td>Manufacturer\'s Warranty - Parts</td><td>' . e($warrantyParts) . '</td></tr>';
            }
            $html .= '</table></section>';
        }

        return $html;
    }

    // ── LOB validation ───────────────────────────────────────────────────

    /**
     * Returns [lob => [sub_lob, ...]] for any pd_lob not in KNOWN_LOBS.
     */
    private function validateLobs(array $groups): array
    {
        $unknown = [];
        foreach ($groups as $rows) {
            $lob    = $this->cell($rows[0], 'lob') ?? '';
            $subLob = $this->cell($rows[0], 'sub_lob') ?? '';
            if ($lob && ! array_key_exists($lob, self::KNOWN_LOBS)) {
                $unknown[$lob][] = $subLob;
            }
        }
        // Deduplicate sub-lobs per unknown lob
        return array_map('array_unique', $unknown);
    }

    /**
     * Upsert lob_display_collection from imported product groups.
     * Only CREATES new records — never overwrites existing customisations (title, image, etc.).
     */
    private function syncLobDisplayCollections(array $groups): void
    {
        $seen = []; // avoid duplicate work within same import

        foreach ($groups as $rows) {
            $lob    = $this->cell($rows[0], 'lob') ?? '';
            $subLob = $this->cell($rows[0], 'sub_lob') ?? '';

            if (! $lob || ! $subLob) {
                continue;
            }

            $slug = $this->gallerySlug($subLob); // reuse Thai-safe slug method
            $key  = "{$lob}|{$slug}";

            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $exists = LobDisplayCollection::where('ldc_slug', $slug)->exists();
            if ($exists) {
                continue; // preserve existing manual config
            }

            $buttonLabel = self::KNOWN_LOBS[$lob] ?? 'สั่งซื้อ';

            // Pull sale_date from the earliest published_at in this sub_lob group
            $saleDate = Product::where('pd_lob', $lob)
                ->where('pd_sub_lob', $subLob)
                ->whereNotNull('published_at')
                ->min('published_at');

            LobDisplayCollection::create([
                'ldc_lob'          => $lob,
                'ldc_sub_lob'      => $subLob,
                'ldc_slug'         => $slug,
                'ldc_title'        => $subLob,
                'ldc_button_label' => $buttonLabel !== 'สั่งซื้อ' ? $buttonLabel : null,
                'ldc_sale_date'    => $saleDate ? \Illuminate\Support\Carbon::parse($saleDate)->toDateString() : null,
                'ldc_is_active'    => true,
                'ldc_is_featured'  => false,
                'ldc_sort_order'   => 0,
            ]);

            $this->line("  [lob-sync] created: {$lob} / {$subLob} → {$slug} (btn: {$buttonLabel})");
        }
    }

    /**
     * Stable URL-safe slug for a color name.
     * Str::slug() strips non-ASCII (Thai) entirely → empty string → gallery key collision.
     * Fallback: hash-based slug so Thai colors each get a unique, stable identifier.
     */
    private function gallerySlug(string $colorName): string
    {
        $slug = Str::slug($colorName);
        return $slug !== '' ? $slug : 'color-' . substr(md5($colorName), 0, 8);
    }

    private function cell(array $row, string $key): ?string
    {
        $idx = self::PRODUCTS_COLS[$key] ?? null;
        if ($idx === null) {
            return null;
        }
        $val = $row[$idx] ?? null;
        if ($val === null || $val === '') {
            return null;
        }
        return trim((string) $val);
    }
}
