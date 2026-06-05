<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use App\Models\ProductGallery;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ProductVariantForm
{
    public static function configure(Schema $schema, ?Product $ownerRecord = null): Schema
    {
        $optionSection = self::buildOptionSection($ownerRecord);

        return $schema->columns(1)->components([

            // ── Option values (dynamic from product's option definitions) ──
            $optionSection,

            // ── Identity ──────────────────────────────────────────────────
            Section::make('Identity')
                ->columns(2)
                ->schema([
                    TextInput::make('pv_title')
                        ->label('Variant title')
                        ->required()
                        ->placeholder('Black / 256GB / 42mm')
                        ->columnSpanFull(),

                    TextInput::make('pv_mpn')
                        ->label('MPN (Apple part number)')
                        ->placeholder('MWWF3QF/A')
                        ->maxLength(100)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $set, callable $get) {
                            if (filled($state) && blank($get('pv_handle'))) {
                                $set('pv_handle', Str::slug($state));
                            }
                        }),

                    TextInput::make('pv_handle')
                        ->label('URL slug')
                        ->placeholder('iphone-17-mg6m4zp-a')
                        ->helperText('/products/{slug} — URL path ของ variant นี้')
                        ->prefix('/products/')
                        ->maxLength(255),

                    TextInput::make('pv_sku')
                        ->label('SKU')
                        ->maxLength(100),

                    TextInput::make('pv_barcode')
                        ->label('Barcode / GTIN')
                        ->maxLength(100),
                ]),

            // ── Gallery ───────────────────────────────────────────────────
            Section::make('Gallery')
                ->description('เลือก Gallery ที่ใช้แสดงรูปสำหรับ variant นี้ — จัดการ Gallery ได้ที่ tab "Galleries"')
                ->schema([
                    Select::make('pg_id')
                        ->label('Use gallery')
                        ->options(fn () => $ownerRecord
                            ? $ownerRecord->galleries()->pluck('pg_name', 'pg_id')
                            : []
                        )
                        ->nullable()
                        ->native(false)
                        ->placeholder('— No gallery (use product default images) —')
                        ->live()
                        ->columnSpanFull(),

                    Placeholder::make('gallery_preview')
                        ->label('Preview (first 5 images)')
                        ->columnSpanFull()
                        ->content(function (Get $get) use ($ownerRecord): HtmlString {
                            $pgId = $get('pg_id');
                            if (! $pgId || ! $ownerRecord) {
                                return new HtmlString('<p class="text-sm text-gray-400">No gallery selected</p>');
                            }
                            $gallery = $ownerRecord->galleries()->where('pg_id', $pgId)->first();
                            if (! $gallery) {
                                return new HtmlString('<p class="text-sm text-gray-400">Gallery not found</p>');
                            }
                            $images = $gallery->media()->where('pm_type', 'image')->limit(5)->get();
                            if ($images->isEmpty()) {
                                return new HtmlString('<p class="text-sm text-gray-400">' . e($gallery->pg_name) . ' — ยังไม่มีรูป</p>');
                            }
                            $html = '<div class="flex gap-2 flex-wrap">';
                            foreach ($images as $img) {
                                $html .= '<img src="' . e($img->pm_src) . '" class="h-20 w-20 object-cover rounded border border-gray-200" loading="lazy" />';
                            }
                            $html .= '</div>';
                            $html .= '<p class="text-xs text-gray-400 mt-1">' . e($gallery->pg_name) . ' — ' . $gallery->media()->count() . ' items total</p>';
                            return new HtmlString($html);
                        }),
                ]),

            // ── Pricing ───────────────────────────────────────────────────
            Section::make('Pricing')
                ->columns(3)
                ->schema([
                    TextInput::make('price')
                        ->label('Price (THB)')
                        ->numeric()
                        ->required()
                        ->prefix('฿')
                        ->placeholder('59900'),

                    TextInput::make('compare_at_price')
                        ->label('Compare at price')
                        ->numeric()
                        ->prefix('฿'),

                    TextInput::make('pv_weight')
                        ->label('Weight')
                        ->numeric()
                        ->suffix('kg'),
                ]),

            // ── CaaS fields ───────────────────────────────────────────────
            Section::make('CaaS / Extended')
                ->collapsible()
                ->collapsed()
                ->columns(2)
                ->schema([
                    TextInput::make('pv_caas_title')
                        ->label('CaaS full title')
                        ->maxLength(500)
                        ->columnSpanFull(),

                    TextInput::make('pv_color_swatch')
                        ->label('Color swatch URL')
                        ->maxLength(1000),

                    TextInput::make('pv_band_swatch')
                        ->label('Band swatch URL')
                        ->maxLength(1000),
                ]),

            // ── Availability & Fulfillment ────────────────────────────────
            Section::make('Availability & Fulfillment')
                ->columns(2)
                ->schema([
                    Toggle::make('pv_available')
                        ->label('Available for sale')
                        ->default(true),

                    Toggle::make('is_pickup_available')
                        ->label('Store pickup available')
                        ->default(true),

                    Toggle::make('requires_shipping')
                        ->label('Requires shipping')
                        ->default(true)
                        ->columnSpan(2),

                    TextInput::make('delivery_lead_time')
                        ->label('Delivery lead time')
                        ->maxLength(100)
                        ->placeholder('จัดส่งภายใน 2-3 วัน')
                        ->helperText('ข้อความแสดงบนหน้าสินค้า')
                        ->columnSpan(2),

                    Placeholder::make('total_stock_display')
                        ->label('Current stock (live from inventory)')
                        ->content(fn ($record): string =>
                            $record
                                ? number_format($record->getTotalStockAttribute()) . ' units (across all stores)'
                                : 'Save variant first to see stock'
                        )
                        ->columnSpan(2),
                ]),
        ]);
    }

    private static function buildOptionSection(?Product $ownerRecord): Section
    {
        $options = $ownerRecord?->options()->orderBy('po_position')->get() ?? collect();

        if ($options->isEmpty()) {
            $fields = [
                Grid::make(3)->schema([
                    TextInput::make('pv_option1')->label('Option 1')->placeholder('e.g. Black'),
                    TextInput::make('pv_option2')->label('Option 2')->placeholder('e.g. 256GB'),
                    TextInput::make('pv_option3')->label('Option 3')->placeholder('e.g. 42mm'),
                ]),
            ];
        } else {
            $fieldList = $options->take(7)->map(function ($option, $index) {
                $fieldName = 'pv_option' . ($index + 1);
                $values    = $option->po_values ?? [];

                if (! empty($values)) {
                    return TextInput::make($fieldName)
                        ->label($option->po_name)
                        ->datalist($values)
                        ->placeholder('Select ' . $option->po_name);
                }

                return TextInput::make($fieldName)
                    ->label($option->po_name)
                    ->placeholder($option->po_name . ' value');
            })->all();

            $fields = [
                Grid::make(min(count($fieldList), 4))->schema($fieldList),
            ];
        }

        return Section::make('Option values')
            ->description('ค่า option ตามที่กำหนดใน product catalog')
            ->schema($fields);
    }
}
