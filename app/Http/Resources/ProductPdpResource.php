<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Transforms a Product model into the nested JSON consumed by the React PDP.
 *
 * Data sources (what comes from where):
 *
 *   DB — always derived:
 *     name, sku, barcode, price, available, badge, description, features,
 *     inBox, warranty, warrantyDetails, media, colors (via variants + options)
 *
 *   pdp_content JSON (Filament Builder) — only what DB cannot provide:
 *     tagline, size, currency, monthlyTerm, defaultColor, appleCarePrice,
 *     processor/memory/storage priceAdd + sublabel, specs table, bundleItems
 *
 * Eager loads required on calling query:
 *   ->with(['productLevelMedia', 'inbox', 'variants', 'options'])
 */
class ProductPdpResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $blocks  = $this->parsePdpBlocks();
        $meta    = $blocks['page_meta'] ?? [];
        $colors  = $this->buildColors();

        $cheapest = $this->variants
            ->where('pv_available', true)
            ->sortBy('price')
            ->first();

        return [
            'id'       => $this->pd_handle,
            'name'     => $this->pd_primary_title ?? $this->pd_name,
            'size'     => $meta['size'] ?? null,
            'badge'    => $this->pd_badge,
            'tagline'  => $meta['tagline'] ?? null,
            'sku'      => $cheapest?->pv_sku,
            'barcode'  => $cheapest?->pv_barcode,

            // Sub-LOB info for FamilyStripe siblings fetch
            'subLob'     => $this->pd_sub_lob,
            'subLobSlug' => $this->pd_sub_lob ? \Illuminate\Support\Str::slug($this->pd_sub_lob) : null,
            'lob'        => $this->pd_lob,

            'media'    => $this->buildMedia(),

            'price'        => (float) ($cheapest?->price ?? $this->price ?? 0),
            'currency'     => $meta['currency'] ?? 'THB',
            'monthlyPrice' => $this->calcMonthly($meta, $cheapest?->price ?? $this->price),
            'monthlyTerm'  => (int) ($meta['monthly_term'] ?? 10),
            'available'    => $this->variants->where('pv_available', true)->isNotEmpty(),

            'defaultColor' => $meta['default_color'] ?? ($colors[0]['id'] ?? null),
            'colors'       => $colors,

            'processors' => $this->buildConfigurator($blocks['configurator_processors']['items'] ?? []),
            'memory'     => $this->buildConfigurator($blocks['configurator_memory']['items'] ?? []),
            'storage'    => $this->buildConfigurator($blocks['configurator_storage']['items'] ?? []),

            'appleCarePrice' => ($p = (int) ($meta['apple_care_price'] ?? 0)) > 0 ? $p : null,
            'bundleItems'    => $this->buildBundleItems($blocks['bundle_items']['items'] ?? []),

            'specs'          => $blocks['specs_table']['items'] ?? [],

            'description'    => $this->pd_description,
            'features'       => $this->buildFeatures(),
            'inBox'          => $this->inbox->sortBy('pib_position')->pluck('pib_text')->values()->all(),
            'warranty'       => $this->pd_warranty_parts,
            'warrantyDetails' => $this->buildWarrantyDetails(),

            'contentSections' => $this->pd_content_sections ?? [],
        ];
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function parsePdpBlocks(): array
    {
        $map = [];
        foreach ($this->pdp_content ?? [] as $block) {
            if (isset($block['type'])) {
                $map[$block['type']] = $block['data'] ?? [];
            }
        }
        return $map;
    }

    /**
     * Derive colors from product_variant rows — no manual entry needed.
     *
     * Lookup chain (po_position → pv_option{n}):
     *   Finds the option named "Color" (case-insensitive) from product_option.
     *   Falls back to pv_option1 if none found.
     *
     * Swatch image → pv_color_swatch (image URL, per uficon guideline).
     * Hex is intentionally omitted — use image swatches only.
     */
    private function buildColors(): array
    {
        $colorOpt = $this->options
            ->first(fn ($o) => stripos($o->po_name, 'color') !== false
                             || stripos($o->po_name, 'สี') !== false);

        $position = $colorOpt ? $colorOpt->po_position : 1;
        $field    = "pv_option{$position}";

        return $this->variants
            ->whereNotNull($field)
            ->where('pv_available', true)
            ->sortBy('price')
            ->unique($field)
            ->map(fn ($v) => array_filter([
                // Thai Slug Fallback Rule: Str::slug() strips Thai → empty.
                // Must match ProductGallery.pg_slug exactly for gallery image switching.
                'id'       => Str::slug($v->$field) ?: 'color-' . substr(md5($v->$field), 0, 8),
                'name'     => $v->$field,
                'imageSrc' => $v->pv_color_swatch ?: null,
            ], fn ($val) => $val !== null))
            ->values()
            ->all();
    }

    private function buildMedia(): array
    {
        return $this->productLevelMedia->map(function ($m) {
            $item = ['type' => $m->pm_type, 'src' => $m->pm_src, 'alt' => $m->pm_alt ?? ''];
            if ($m->pm_type === 'video' && ! empty($m->pm_poster)) {
                $item['poster'] = $m->pm_poster;
            }
            return $item;
        })->values()->all();
    }

    /** Normalises Filament Builder snake_case → React camelCase */
    private function buildConfigurator(array $items): array
    {
        return array_map(fn ($item) => array_filter([
            'id'       => $item['id'],
            'label'    => $item['label'],
            'sublabel' => $item['sublabel'] ?? null,
            'priceAdd' => (int) ($item['price_add'] ?? 0),
        ], fn ($v) => $v !== null), $items);
    }

    private function buildBundleItems(array $items): array
    {
        return array_map(fn ($item) => [
            'id'       => $item['id'],
            'name'     => $item['name'],
            'price'    => (int) ($item['price'] ?? 0),
            'imageSrc' => $item['image_src'] ?? '',
        ], $items);
    }

    /** Strip RichEditor HTML → clean string array for React features list */
    private function buildFeatures(): array
    {
        if (empty($this->pd_features)) return [];

        $text = preg_replace('/<\/?(p|li|br)[^>]*>/i', "\n", $this->pd_features);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return array_values(array_filter(
            array_map('trim', explode("\n", $text)),
            fn ($line) => mb_strlen($line) > 0,
        ));
    }

    private function buildWarrantyDetails(): array
    {
        $details = [];
        if (! empty($this->pd_warranty_parts)) {
            $details[] = ['label' => 'การรับประกันของผู้ผลิต - ค่าแรง', 'value' => $this->pd_warranty_parts];
        }
        if (! empty($this->pd_warranty_labor)) {
            $details[] = ['label' => 'การรับประกันของผู้ผลิต - ชิ้นส่วน', 'value' => $this->pd_warranty_labor];
        }
        return $details;
    }

    private function calcMonthly(array $meta, mixed $price): int
    {
        $term = max(1, (int) ($meta['monthly_term'] ?? 10));
        return (int) round((float) ($price ?? 0) / $term);
    }
}
