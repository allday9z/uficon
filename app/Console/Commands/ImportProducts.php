<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\ImportColumnMap;
use App\Models\ImportProfile;
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
 * Profile-driven product import.
 * Column mapping is stored in import_profiles / import_column_maps tables.
 *
 * Usage:
 *   php artisan products:import --profile=caas /path/to/file.xlsx
 *   php artisan products:import --profile=caas /path/to/file.xlsx --dry-run
 *   php artisan products:import --profile=caas /path/to/file.xlsx --fresh
 */
class ImportProducts extends Command
{
    protected $signature = 'products:import
                            {file : Absolute path to the import file (.xlsx)}
                            {--profile=caas : Import profile slug (from import_profiles table)}
                            {--dry-run : Parse and validate without writing to DB}
                            {--confirm : Preview what will change and ask for confirmation before writing}
                            {--fresh : Delete existing products matching imported primary titles before import}
                            {--skip-existing : Insert new products only — do not update existing ones}';

    protected $description = 'Import products from xlsx using a profile-driven column mapping';

    private ImportProfile $profile;
    /** @var ImportColumnMap[] indexed by "Model.field" */
    private array $mapIndex = [];
    private array $requiredCols = [];

    public int $createdCount = 0;
    public int $updatedCount = 0;
    public int $skippedCount = 0;

    public function handle(): int
    {
        $filePath     = $this->argument('file');
        $profileSlug  = $this->option('profile');
        $isDryRun     = $this->option('dry-run');
        $isConfirm    = $this->option('confirm');
        $isFresh      = $this->option('fresh');
        $skipExisting = $this->option('skip-existing');

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return self::FAILURE;
        }

        // ── Load profile ─────────────────────────────────────────────────
        $profile = ImportProfile::where('ip_slug', $profileSlug)->with('columnMaps')->first();
        if (! $profile) {
            $this->error("Import profile not found: {$profileSlug}");
            $this->line('Available: ' . ImportProfile::pluck('ip_slug')->join(', '));
            return self::FAILURE;
        }

        $this->profile = $profile;
        $this->buildMapIndex();

        $this->info("Profile: {$profile->ip_name} ({$profile->ip_slug}) — {$profile->columnMaps->count()} column maps");

        if ($isDryRun) {
            $this->warn('[DRY RUN] No changes will be written to DB.');
        }

        // ── Read file ─────────────────────────────────────────────────────
        [$collections, $productRows] = $this->readSheets($filePath);

        $this->info(sprintf('Parsed: %d collection rows, %d product rows', count($collections), count($productRows)));

        // ── Validate required columns ─────────────────────────────────────
        if (! empty($productRows) && ! $this->validateRequiredCols($productRows[0])) {
            return self::FAILURE;
        }

        // ── Group by primary title ────────────────────────────────────────
        $groups = [];
        foreach ($productRows as $row) {
            $key = $this->get($row, 'Product', 'pd_primary_title');
            if (! $key) {
                continue;
            }
            $groups[$key][] = $row;
        }

        // ── Preview + Confirm ─────────────────────────────────────────────
        $this->showPreview($groups, $isFresh, $skipExisting);

        if ($isDryRun) {
            $this->info('[DRY RUN] Done — no DB writes.');
            return self::SUCCESS;
        }

        if ($isConfirm && ! $this->confirm('Proceed with import?', false)) {
            $this->warn('Import cancelled.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($collections, $groups, $isFresh, $skipExisting) {
            $this->processCollections($collections);

            if ($isFresh) {
                $titles = array_keys($groups);
                $count  = Product::whereIn('pd_primary_title', $titles)->count();
                Product::whereIn('pd_primary_title', $titles)->forceDelete();
                $this->warn("--fresh: deleted {$count} existing products.");
            }

            foreach ($groups as $primaryTitle => $rows) {
                $this->importProductGroup($primaryTitle, $rows, $skipExisting);
            }
        });

        $this->info(sprintf(
            '✅ Import complete — created %d, updated %d, skipped %d',
            $this->createdCount,
            $this->updatedCount,
            $this->skippedCount,
        ));

        return self::SUCCESS;
    }

    // ── Preview ──────────────────────────────────────────────────────────

    private function showPreview(array $groups, bool $isFresh, bool $skipExisting): void
    {
        $this->newLine();
        $this->line('┌─ Import Preview ──────────────────────────────────────────────────┐');

        $rows     = [];
        $newCount = 0;
        $upCount  = 0;
        $skipCount = 0;

        foreach ($groups as $primaryTitle => $variantRows) {
            $handle   = Str::slug($primaryTitle);
            $existing = Product::withTrashed()->where('pd_handle', $handle)->first();

            if (! $existing) {
                $action = '<fg=green>CREATE</>';
                $detail = count($variantRows) . ' variants (new)';
                $newCount++;
            } elseif ($skipExisting) {
                $action = '<fg=yellow>SKIP</>';
                $detail = 'already exists';
                $skipCount++;
            } elseif ($isFresh) {
                $action = '<fg=red>DELETE→CREATE</>';
                $detail = count($variantRows) . ' variants (fresh)';
                $upCount++;
            } else {
                // Diff changed fields
                $firstRow      = $variantRows[0];
                $changedFields = $this->diffProductFields($existing, $firstRow);
                $variantStats  = $this->diffVariants($existing, $variantRows);
                $detail = implode(', ', array_filter([
                    ! empty($changedFields) ? count($changedFields) . ' field(s) changed' : null,
                    $variantStats['new'] > 0 ? "+{$variantStats['new']} variants" : null,
                    $variantStats['updated'] > 0 ? "~{$variantStats['updated']} variants updated" : null,
                    $variantStats['new'] === 0 && $variantStats['updated'] === 0 && empty($changedFields) ? 'no changes' : null,
                ])) ?: 'no changes';
                $action = '<fg=cyan>UPDATE</>';
                $upCount++;
            }

            $rows[] = [$action, $primaryTitle, count($variantRows) . ' variants', $detail];
        }

        $this->table(
            ['Action', 'Product (Primary Title)', 'Variants', 'Detail'],
            $rows
        );

        $this->line(sprintf(
            '  <fg=green>CREATE: %d</>  <fg=cyan>UPDATE: %d</>  <fg=yellow>SKIP: %d</>  Total products: %d',
            $newCount, $upCount, $skipCount, count($groups)
        ));
        $this->newLine();
    }

    /** Compare product-level CaaS fields against existing record — return list of changed field names */
    private function diffProductFields(Product $existing, array $firstRow): array
    {
        $changed = [];
        $caasFields = ['pd_primary_title', 'pd_secondary_title', 'pd_description',
                       'pd_features', 'pd_base_name', 'pd_lob', 'pd_sub_lob',
                       'pd_warranty_parts', 'pd_warranty_labor'];

        foreach ($caasFields as $field) {
            $incoming = $this->get($firstRow, 'Product', $field);
            $current  = $existing->{$field};
            if ((string) $incoming !== (string) $current && ! ($incoming === null && $current === null)) {
                $changed[] = $field;
            }
        }
        return $changed;
    }

    /** Count new vs updated variants for a product group */
    private function diffVariants(Product $existing, array $variantRows): array
    {
        $new     = 0;
        $updated = 0;
        foreach ($variantRows as $row) {
            $mpn = $this->get($row, 'ProductVariant', 'pv_mpn');
            if (! $mpn) {
                continue;
            }
            ProductVariant::withTrashed()->where('pv_mpn', $mpn)->exists()
                ? $updated++
                : $new++;
        }
        return ['new' => $new, 'updated' => $updated];
    }

    // ── Map index helpers ────────────────────────────────────────────────

    private function buildMapIndex(): void
    {
        foreach ($this->profile->columnMaps as $map) {
            $this->mapIndex["{$map->icm_target_model}.{$map->icm_target_field}"] = $map;
            if ($map->icm_required) {
                $this->requiredCols[] = $map;
            }
        }
    }

    /** Get resolved value for a given model+field from a row */
    private function get(array $row, string $model, string $field): mixed
    {
        $map = $this->mapIndex["{$model}.{$field}"] ?? null;
        return $map ? $map->resolveFrom($row) : null;
    }

    /** Build an array of [field => value] for a model, applying update_mode filter */
    private function buildFields(array $row, string $model, bool $isUpdate): array
    {
        $result = [];
        foreach ($this->profile->columnMaps->where('icm_target_model', $model) as $map) {
            if ($isUpdate && $map->icm_update_mode === 'create_only') {
                continue;
            }
            if ($map->icm_update_mode === 'skip') {
                continue;
            }
            // Media/inbox/collection maps are handled separately
            if (in_array($model, ['ProductMedia', 'ProductInbox', 'ProductCollection', 'Brand'])) {
                continue;
            }
            $value = $map->resolveFrom($row);
            if ($value !== null || $map->icm_default_value !== null) {
                $result[$map->icm_target_field] = $value;
            }
        }
        return $result;
    }

    // ── Required column validation ────────────────────────────────────────

    private function validateRequiredCols(array $sampleRow): bool
    {
        $missing = [];
        foreach ($this->requiredCols as $map) {
            if ($map->icm_source_index !== null) {
                $val = $sampleRow[$map->icm_source_index] ?? null;
                if ($val === null || trim((string) $val) === '') {
                    $missing[] = "{$map->icm_target_field} (col {$map->icm_source_index}: {$map->icm_source_header})";
                }
            }
        }
        if (! empty($missing)) {
            $this->error('Required columns missing or empty in first data row:');
            foreach ($missing as $m) {
                $this->line("  - {$m}");
            }
            return false;
        }
        return true;
    }

    // ── Sheet reading ────────────────────────────────────────────────────

    private function readSheets(string $filePath): array
    {
        $sheetFilter = $this->profile->ip_sheet_name;
        $reader      = new Reader();
        $reader->open($filePath);

        $collections = [];
        $productRows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $name         = $sheet->getName();
            $isProducts   = $sheetFilter
                ? str_contains($name, $sheetFilter) && ! str_contains($name, 'Collection')
                : ! str_contains($name, 'Collection');
            $isCollection = str_contains($name, 'Collection');

            $rowIndex = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $cells = array_map(fn ($c) => $c->getValue(), $row->getCells());
                $rowIndex++;
                if ($rowIndex <= $this->profile->ip_header_row) {
                    continue;
                }
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

    private const COLLECTION_COLS = [
        'title' => 0, 'handle' => 1, 'rule_col' => 2, 'rule_rel' => 3, 'rule_cond' => 4,
        'opt1_label' => 5, 'opt2_label' => 6, 'opt3_label' => 7, 'opt4_label' => 8,
        'opt5_label' => 9, 'opt6_label' => 10, 'opt7_label' => 11,
        'opt1_values' => 12, 'opt2_values' => 13, 'opt3_values' => 14, 'opt4_values' => 15,
        'opt5_values' => 16, 'opt6_values' => 17, 'opt7_values' => 18,
    ];

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
                $label = trim((string) ($row[$cols["opt{$i}_label"]] ?? ''));
                $val   = trim((string) ($row[$cols["opt{$i}_values"]] ?? ''));
                if ($label) $labels[$i] = $label;
                if ($val)   $values[$i] = $val;
            }
            ProductCollection::updateOrCreate(
                ['pcol_handle' => $handle],
                [
                    'pcol_title'          => trim((string) ($row[$cols['title']] ?? '')),
                    'pcol_rule_column'    => trim((string) ($row[$cols['rule_col']] ?? '')) ?: null,
                    'pcol_rule_relation'  => trim((string) ($row[$cols['rule_rel']] ?? '')) ?: null,
                    'pcol_rule_condition' => trim((string) ($row[$cols['rule_cond']] ?? '')) ?: null,
                    'pcol_option_labels'  => $labels ?: null,
                    'pcol_option_values'  => $values ?: null,
                ]
            );
            $this->line("  [collection] upserted: {$handle}");
        }
    }

    // ── Product group import ─────────────────────────────────────────────

    private function importProductGroup(string $primaryTitle, array $rows, bool $skipExisting): void
    {
        $firstRow = $rows[0];

        // Brand
        $vendorName = $this->get($firstRow, 'Brand', 'brand_name') ?? 'Unknown';
        $brand = Brand::firstOrCreate(
            ['brand_name' => $vendorName],
            ['brand_code' => Str::slug($vendorName)]
        );

        // Collection
        $collectionHandle = $this->get($firstRow, 'ProductCollection', 'pcol_handle');
        $collection = ProductCollection::firstOrCreate(
            ['pcol_handle' => $collectionHandle],
            ['pcol_title'  => $primaryTitle ?: $collectionHandle]
        );

        // Product upsert
        $handle          = Str::slug($primaryTitle);
        $existingProduct = Product::withTrashed()->where('pd_handle', $handle)->first();
        $isUpdate        = (bool) $existingProduct;

        if ($isUpdate && $skipExisting) {
            $this->skippedCount++;
            $this->line("  [skip] {$primaryTitle}");
            return;
        }

        $productFields = $this->buildFields($firstRow, 'Product', $isUpdate);
        $productFields['brand_id'] = $brand->brand_id;
        $productFields['pcol_id']  = $collection->pcol_id;
        $productFields['pd_name']  = $primaryTitle;
        $productFields['pd_template_type'] = $this->resolveTemplateType($productFields['pd_type'] ?? null);

        if ($isUpdate) {
            if ($existingProduct->trashed()) {
                $existingProduct->restore();
            }
            $existingProduct->fill($productFields)->save();
            $product = $existingProduct;
        } else {
            $productFields['pd_status']    ??= 'active';
            $productFields['price']        ??= 0;
            $productFields['published_at']   = now();
            $product = Product::create(array_merge(['pd_handle' => $handle], $productFields));
        }

        $isUpdate ? $this->updatedCount++ : $this->createdCount++;

        // Rebuild child records on update
        if ($isUpdate) {
            $product->options()->delete();
            $product->inbox()->delete();
            $product->productLevelMedia()->delete();
        }

        // Option definitions from collection
        $optionLabels = $collection->pcol_option_labels ?? [];
        foreach ($optionLabels as $idx => $label) {
            ProductOption::create([
                'pd_id'       => $product->pd_id,
                'po_name'     => $label,
                'po_display'  => $this->resolveOptionDisplay($label),
                'po_position' => (int) $idx,
            ]);
        }

        // Inbox items
        $inboxPosition = 1;
        foreach (['inbox1', 'inbox2', 'inbox3', 'inbox4'] as $key) {
            $text = $this->get($firstRow, 'ProductInbox', $key);
            if ($text) {
                ProductInbox::create([
                    'pd_id'        => $product->pd_id,
                    'pib_text'     => $text,
                    'pib_position' => $inboxPosition++,
                ]);
            }
        }

        // Product-level media (img1-15 + videos)
        $mediaPosition = 1;
        for ($i = 1; $i <= 15; $i++) {
            $url = $this->get($firstRow, 'ProductMedia', "img{$i}");
            if ($url) {
                ProductMedia::create([
                    'pd_id'       => $product->pd_id,
                    'pv_id'       => null,
                    'pm_src'      => $url,
                    'pm_type'     => 'image',
                    'pm_position' => $mediaPosition++,
                    'pm_alt'      => $primaryTitle,
                ]);
            }
        }
        foreach (['video1', 'video2', 'video3'] as $key) {
            $url = $this->get($firstRow, 'ProductMedia', $key);
            if ($url) {
                ProductMedia::create([
                    'pd_id'       => $product->pd_id,
                    'pv_id'       => null,
                    'pm_src'      => $url,
                    'pm_type'     => 'video',
                    'pm_position' => $mediaPosition++,
                    'pm_alt'      => null,
                ]);
            }
        }

        // Overview HTML
        $product->pd_overview = $this->buildOverviewHtml(
            $this->get($firstRow, 'Product', 'pd_description'),
            $this->get($firstRow, 'Product', 'pd_features'),
            array_filter(array_map(fn ($k) => $this->get($firstRow, 'ProductInbox', $k), ['inbox1', 'inbox2', 'inbox3', 'inbox4'])),
            $this->get($firstRow, 'Product', 'pd_warranty_labor'),
            $this->get($firstRow, 'Product', 'pd_warranty_parts')
        );
        $product->save();

        // Variants
        foreach ($rows as $row) {
            $this->importVariant($product, $row, $isUpdate);
        }

        $this->info(sprintf(
            '  [product] %s — %d variants, %d media',
            $primaryTitle,
            count($rows),
            $mediaPosition - 1,
        ));
    }

    // ── Single variant import ────────────────────────────────────────────

    private function importVariant(Product $product, array $row, bool $isProductUpdate): void
    {
        $mpn = $this->get($row, 'ProductVariant', 'pv_mpn');
        if (! $mpn) {
            return;
        }

        $options = array_filter([
            $this->get($row, 'ProductVariant', 'pv_option1'),
            $this->get($row, 'ProductVariant', 'pv_option2'),
            $this->get($row, 'ProductVariant', 'pv_option3'),
            $this->get($row, 'ProductVariant', 'pv_option4'),
            $this->get($row, 'ProductVariant', 'pv_option5'),
            $this->get($row, 'ProductVariant', 'pv_option6'),
            $this->get($row, 'ProductVariant', 'pv_option7'),
        ]);

        $existingVariant = ProductVariant::withTrashed()->where('pv_mpn', $mpn)->first();
        $isVariantUpdate = (bool) $existingVariant;

        $variantFields = $this->buildFields($row, 'ProductVariant', $isVariantUpdate);
        $variantFields['pd_id']    = $product->pd_id;
        $variantFields['pv_title'] = implode(' / ', $options) ?: $this->get($row, 'ProductVariant', 'pv_caas_title');
        $variantFields['pv_available']    ??= true;
        $variantFields['requires_shipping'] ??= true;
        $variantFields['taxable']           ??= true;

        // Gallery by color (opt1)
        $colorName = $this->get($row, 'ProductVariant', 'pv_option1');
        $gallery   = null;
        if ($colorName) {
            $gallery = ProductGallery::firstOrCreate(
                ['pd_id' => $product->pd_id, 'pg_slug' => Str::slug($colorName)],
                ['pg_name' => $colorName, 'pg_position' => 0]
            );
            $variantFields['pg_id'] = $gallery->pg_id;
        }

        if ($existingVariant) {
            if ($existingVariant->trashed()) {
                $existingVariant->restore();
            }
            $existingVariant->fill($variantFields)->save();
        } else {
            $variantFields['price'] ??= 0;
            ProductVariant::create(array_merge(['pv_mpn' => $mpn], $variantFields));
        }

        // Swatch media (dedup by src+type)
        $colorSwatch = $this->get($row, 'ProductVariant', 'pv_color_swatch');
        if ($colorSwatch && ! ProductMedia::where('pd_id', $product->pd_id)->where('pm_src', $colorSwatch)->where('pm_type', 'swatch')->exists()) {
            ProductMedia::create([
                'pd_id' => $product->pd_id, 'pg_id' => $gallery?->pg_id,
                'pm_src' => $colorSwatch, 'pm_type' => 'swatch', 'pm_position' => 1, 'pm_alt' => $colorName,
            ]);
        }

        $bandSwatch = $this->get($row, 'ProductVariant', 'pv_band_swatch');
        if ($bandSwatch && ! ProductMedia::where('pd_id', $product->pd_id)->where('pm_src', $bandSwatch)->where('pm_type', 'band_swatch')->exists()) {
            ProductMedia::create([
                'pd_id' => $product->pd_id, 'pg_id' => $gallery?->pg_id,
                'pm_src' => $bandSwatch, 'pm_type' => 'band_swatch', 'pm_position' => 2,
                'pm_alt' => $this->get($row, 'ProductVariant', 'pv_option2'),
            ]);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function resolveTemplateType(?string $productType): string
    {
        if (! $productType) return 'normal';
        $lower      = strtolower($productType);
        $fulldetail = ['mac', 'macbook', 'imac', 'mac pro', 'mac mini', 'mac studio', 'ipad', 'laptop'];
        foreach ($fulldetail as $kw) {
            if (str_contains($lower, $kw)) return 'fulldetail';
        }
        return 'normal';
    }

    private function resolveOptionDisplay(string $optionName): string
    {
        $lower = strtolower(trim($optionName));
        return match (true) {
            str_contains($lower, 'band color') || $lower === 'band'                                                   => 'band_swatch',
            str_contains($lower, 'color') || str_contains($lower, 'finish') || str_contains($lower, 'colour')        => 'color_swatch',
            str_contains($lower, 'processor') || str_contains($lower, 'chip') || str_contains($lower, 'cpu')         => 'dropdown',
            default => 'button',
        };
    }

    private function buildOverviewHtml(?string $description, ?string $featuresRaw, array $inboxItems, ?string $warrantyLabor, ?string $warrantyParts): string
    {
        $html = '';
        if (filled($description)) {
            $html .= '<section class="overview-description"><p>' . e($description) . '</p></section>';
        }
        if (filled($featuresRaw)) {
            $bullets = array_filter(array_map('trim', explode('<br>', $featuresRaw)));
            if (! empty($bullets)) {
                $html .= '<section class="overview-features"><ul>' . implode('', array_map(fn ($b) => '<li>' . $b . '</li>', $bullets)) . '</ul></section>';
            }
        }
        if (! empty($inboxItems)) {
            $html .= '<section class="overview-inbox"><ul>' . implode('', array_map(fn ($i) => '<li>' . e($i) . '</li>', $inboxItems)) . '</ul></section>';
        }
        if (filled($warrantyLabor) || filled($warrantyParts)) {
            $html .= '<section class="overview-warranty"><table>';
            if (filled($warrantyLabor)) $html .= '<tr><td>Manufacturer\'s Warranty - Labor</td><td>' . e($warrantyLabor) . '</td></tr>';
            if (filled($warrantyParts)) $html .= '<tr><td>Manufacturer\'s Warranty - Parts</td><td>' . e($warrantyParts) . '</td></tr>';
            $html .= '</table></section>';
        }
        return $html;
    }
}
