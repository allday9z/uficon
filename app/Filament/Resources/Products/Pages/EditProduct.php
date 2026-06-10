<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected static ?string $navigationLabel = 'Catalog';

    protected static ?string $title = 'Edit product';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_pdp_content')
                ->label('Auto-generate PDP Content')
                ->icon(Heroicon::Sparkles)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Auto-generate PDP Content')
                ->modalDescription('สร้าง configurator blocks (processor/memory/storage) จาก variant data + ดึง tagline อัตโนมัติ — จะ overwrite pdp_content ที่มีอยู่')
                ->action(function () {
                    /** @var Product $product */
                    $product = $this->getRecord();
                    $product->loadMissing(['variants', 'options']);

                    $generated = $this->generatePdpContent($product);
                    $product->pdp_content = $generated;
                    $product->save();

                    $this->fillForm();

                    Notification::make()
                        ->title('PDP Content generated — ตรวจสอบใน Tab Content แล้วบันทึก')
                        ->success()
                        ->send();
                }),

            DeleteAction::make(),
        ];
    }

    // ── PDP Content auto-generator ───────────────────────────────────────

    private function generatePdpContent(Product $product): array
    {
        $blocks     = $product->pdp_content ?? [];
        $options    = $product->options->sortBy('po_position');
        $variants   = $product->variants->where('pv_available', true)->sortBy('price');
        $basePrice  = (float) ($variants->min('price') ?? 0);

        // ── page_meta ─────────────────────────────────────────────────────
        $existingMeta = $this->findBlock($blocks, 'page_meta');
        $tagline = $existingMeta['data']['tagline'] ?? $this->resolveTagline($product);
        $blocks = $this->upsertBlock($blocks, 'page_meta', [
            'tagline'     => $tagline,
            'size'        => $existingMeta['data']['size'] ?? null,
            'currency'    => $existingMeta['data']['currency'] ?? 'THB',
            'monthly_term' => $existingMeta['data']['monthly_term'] ?? 10,
            'default_color' => $existingMeta['data']['default_color'] ?? null,
            'apple_care_price' => $existingMeta['data']['apple_care_price'] ?? 0,
        ]);

        // ── configurator blocks (processor / memory / storage) ────────────
        foreach ($options as $opt) {
            $blockType = $this->optionToBlockType($opt->po_name);
            if (! $blockType) continue;

            $existing = $this->findBlock($blocks, $blockType);
            // Keep manual items if they exist AND have more info (sublabel, etc.)
            if (! empty($existing['data']['items'])) continue;

            $items = [];
            foreach ($opt->po_values ?? [] as $value) {
                // Find cheapest variant with this option value
                $optField  = 'pv_option' . $opt->po_position;
                $match     = $variants->first(fn ($v) => $v->$optField === $value);
                $priceAdd  = $match ? (int) round((float) $match->price - $basePrice) : 0;

                $items[] = [
                    'id'        => \Illuminate\Support\Str::slug($value) ?: 'opt-' . substr(md5($value), 0, 6),
                    'label'     => $value,
                    'sublabel'  => null,
                    'price_add' => $priceAdd,
                ];
            }

            $blocks = $this->upsertBlock($blocks, $blockType, ['items' => $items]);
        }

        return $blocks;
    }

    private function resolveTagline(Product $product): ?string
    {
        // 1. Extract first meaningful line from pd_features
        if (filled($product->pd_features)) {
            $text = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $product->pd_features));
            $lines = array_filter(array_map('trim', explode("\n", $text)));
            $first = reset($lines);
            if ($first && mb_strlen($first) < 120) return $first;
        }

        // 2. Fallback: scrape meta description from istudio.store
        try {
            $variant = $product->variants()->whereNotNull('pv_handle')->first();
            if (! $variant?->pv_handle) return null;

            $url  = "https://www.istudio.store/products/{$variant->pv_handle}";
            $html = @file_get_contents($url, false, stream_context_create([
                'http' => ['timeout' => 5, 'header' => "Accept-Language: th-TH\r\n"],
            ]));

            if ($html) {
                if (preg_match('/content="([^"]{15,200})"[^>]*name="description"/i', $html, $m)) {
                    return htmlspecialchars_decode($m[1]);
                }
                if (preg_match('/"description":"([^"]{15,200})"/', $html, $m)) {
                    return html_entity_decode(strip_tags($m[1]));
                }
            }
        } catch (\Throwable) {}

        return null;
    }

    private function optionToBlockType(string $name): ?string
    {
        $lower = strtolower(trim($name));
        return match (true) {
            str_contains($lower, 'processor') || str_contains($lower, 'chip') ||
            str_contains($lower, 'โปรเซสเซอร์') || str_contains($lower, 'ชิป') => 'configurator_processors',
            str_contains($lower, 'memory') || str_contains($lower, 'ram') ||
            str_contains($lower, 'หน่วยความจำ') => 'configurator_memory',
            str_contains($lower, 'storage') || str_contains($lower, 'ความจุ') ||
            str_contains($lower, 'พื้นที่จัดเก็บ') => 'configurator_storage',
            default => null,
        };
    }

    private function findBlock(array $blocks, string $type): array
    {
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === $type) return $block;
        }
        return ['type' => $type, 'data' => []];
    }

    private function upsertBlock(array $blocks, string $type, array $data): array
    {
        $found = false;
        $blocks = array_map(function ($b) use ($type, $data, &$found) {
            if (($b['type'] ?? '') === $type) {
                $found = true;
                return ['type' => $type, 'data' => array_merge($b['data'] ?? [], $data)];
            }
            return $b;
        }, $blocks);
        if (! $found) $blocks[] = ['type' => $type, 'data' => $data];
        return $blocks;
    }
}
