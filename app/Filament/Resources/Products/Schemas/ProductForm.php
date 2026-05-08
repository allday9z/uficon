<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Brand;
use App\Models\OptionName;
use App\Models\ProductType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([

                // ════════════════════════════════════════════════════
                // ROW 1 LEFT — Title + Description
                // ════════════════════════════════════════════════════
                Section::make()
                    ->columnSpan(2)
                    ->schema([
                        TextInput::make('pd_name')
                            ->label('Title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Short sleeve t-shirt')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) =>
                                $set('pd_handle', Str::slug($state))
                            )
                            ->columnSpanFull(),

                        RichEditor::make('pd_description')
                            ->label('Description')
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'strike',
                                'link', 'bulletList', 'orderedList',
                                'h2', 'h3', 'redo', 'undo',
                            ])
                            ->placeholder('Describe the product...')
                            ->columnSpanFull(),
                    ]),

                // ════════════════════════════════════════════════════
                // ROW 1 RIGHT — Status + Publishing
                // ════════════════════════════════════════════════════
                Grid::make(1)
                    ->columnSpan(1)
                    ->schema([
                        Section::make('Status')
                            ->schema([
                                Select::make('pd_status')
                                    ->label('')
                                    ->options([
                                        'active'   => 'Active',
                                        'draft'    => 'Draft',
                                        'inactive' => 'Inactive',
                                    ])
                                    ->default('active')
                                    ->required()
                                    ->native(false),
                            ]),

                        Section::make('Publishing')
                            ->schema([
                                DateTimePicker::make('published_at')
                                    ->label('Published at')
                                    ->native(false)
                                    ->default(now()),
                            ]),
                    ]),

                // ════════════════════════════════════════════════════
                // ROW 2 LEFT — Media
                // ════════════════════════════════════════════════════
                Section::make('Media')
                    ->columnSpan(2)
                    ->description('รูปภาพแรกจะเป็น featured image ของสินค้า')
                    ->schema([
                        Repeater::make('media')
                            ->relationship('media')
                            ->label('')
                            ->schema([
                                FileUpload::make('pm_src')
                                    ->label('รูปภาพ')
                                    ->image()
                                    ->imageEditor()
                                    ->disk('public')
                                    ->directory('images/products')
                                    ->columnSpanFull(),

                                TextInput::make('pm_alt')
                                    ->label('Alt text')
                                    ->placeholder('Describe the image...')
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                TextInput::make('pm_position')
                                    ->label('ลำดับ')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->extraInputAttributes(['type' => 'hidden'])
                                    ->visibleOn('edit'),

                                Select::make('pm_type')
                                    ->default('image')
                                    ->options(['image' => 'image', 'video' => 'video'])
                                    ->extraInputAttributes(['type' => 'hidden'])
                                    ->label('')
                                    ->visibleOn('edit'),
                            ])
                            ->columns(1)
                            ->grid(['default' => 2, 'lg' => 3])
                            ->addActionLabel('+ Add media')
                            ->defaultItems(0)
                            ->deletable()
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),

                // ════════════════════════════════════════════════════
                // ROW 2 RIGHT — Product Organization
                // ════════════════════════════════════════════════════
                Section::make('Product organization')
                    ->columnSpan(1)
                    ->schema([
                        Select::make('brand_id')
                            ->label('Vendor')
                            ->options(fn () => Brand::orderBy('brand_name')->pluck('brand_name', 'brand_id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                TextInput::make('brand_name')->required()->label('Brand name'),
                                TextInput::make('brand_code')->required()->label('Brand code'),
                            ]),

                        Select::make('pd_type')
                            ->label('Product type')
                            ->options(fn () => ProductType::orderBy('sort_order')->pluck('name', 'name'))
                            ->searchable()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Product type name')
                                    ->required()
                                    ->maxLength(100),
                            ])
                            ->createOptionUsing(function (array $data): string {
                                ProductType::firstOrCreate(
                                    ['name' => $data['name']],
                                    ['sort_order' => ProductType::max('sort_order') + 1]
                                );
                                return $data['name'];
                            })
                            ->native(false),

                        TagsInput::make('tag_list')
                            ->label('Tags')
                            ->placeholder('Add tag...')
                            ->separator(','),
                    ]),

                // ════════════════════════════════════════════════════
                // ROW 3 LEFT — Variants
                // ════════════════════════════════════════════════════
                Section::make('Variants')
                    ->columnSpan(2)
                    ->schema([
                        // Option Names
                        Repeater::make('options')
                            ->relationship('options')
                            ->label('Options')
                            ->hint('ชื่อมิติของสินค้า เช่น Color / Storage — แต่ละ Option จะใช้กรอกค่าใน Variant list ด้านล่าง')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('po_name')
                                        ->label('Option name')
                                        ->options(fn () => OptionName::orderBy('sort_order')->pluck('name', 'name'))
                                        ->searchable()
                                        ->createOptionForm([
                                            TextInput::make('name')
                                                ->label('Option name')
                                                ->required()
                                                ->maxLength(100),
                                        ])
                                        ->createOptionUsing(function (array $data): string {
                                            OptionName::firstOrCreate(
                                                ['name' => $data['name']],
                                                ['sort_order' => OptionName::max('sort_order') + 1]
                                        );
                                            return $data['name'];
                                        })
                                        ->required()
                                        ->native(false),

                                    TextInput::make('po_position')
                                        ->label('ลำดับ')
                                        ->numeric()
                                        ->default(1)
                                        ->minValue(1),
                                ]),
                            ])
                            ->addActionLabel('+ Add option')
                            ->itemLabel(fn (array $state): ?string => $state['po_name'] ?? 'Option')
                            ->defaultItems(0)
                            ->reorderableWithButtons()
                            ->columnSpanFull(),

                        // Variant Rows
                        Repeater::make('variants')
                            ->relationship('variants')
                            ->label('Variant list')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('pv_option1')
                                        ->label('Option 1')
                                        ->placeholder('e.g. Space Black'),
                                    TextInput::make('pv_option2')
                                        ->label('Option 2')
                                        ->placeholder('e.g. 512GB'),
                                    TextInput::make('pv_option3')
                                        ->label('Option 3')
                                        ->placeholder('e.g. 16GB RAM'),
                                ]),

                                Grid::make(3)->schema([
                                    TextInput::make('pv_title')
                                        ->label('Variant title')
                                        ->required()
                                        ->placeholder('Space Black / 512GB'),
                                    TextInput::make('pv_sku')
                                        ->label('SKU')
                                        ->placeholder('MDE04TH/A')
                                        ->maxLength(100),
                                    TextInput::make('pv_barcode')
                                        ->label('Barcode / ISBN / GTIN')
                                        ->maxLength(100),
                                ]),

                                Grid::make(3)->schema([
                                    TextInput::make('price')
                                        ->label('Price (THB)')
                                        ->numeric()
                                        ->required()
                                        ->prefix('฿')
                                        ->placeholder('59900'),
                                    TextInput::make('compare_at_price')
                                        ->label('Compare at price')
                                        ->numeric()
                                        ->prefix('฿')
                                        ->placeholder('ราคาเดิม'),
                                    TextInput::make('pv_weight')
                                        ->label('Weight')
                                        ->numeric()
                                        ->suffix('kg'),
                                ]),

                                Grid::make(2)->schema([
                                    Toggle::make('pv_available')
                                        ->label('Available for sale')
                                        ->default(true),
                                    Toggle::make('requires_shipping')
                                        ->label('Requires shipping')
                                        ->default(true),
                                ]),
                            ])
                            ->addActionLabel('+ Add variant')
                            ->itemLabel(fn (array $state): string =>
                                collect([$state['pv_option1'] ?? null, $state['pv_option2'] ?? null, $state['pv_option3'] ?? null])
                                    ->filter()
                                    ->implode(' / ')
                                    ?: ($state['pv_title'] ?? 'New variant')
                            )
                            ->collapsed()
                            ->reorderableWithButtons()
                            ->defaultItems(1)
                            ->columnSpanFull(),

                        // Inventory total (read-only on edit)
                        Placeholder::make('total_inventory')
                            ->label('Total inventory at all locations')
                            ->content(fn ($record): string =>
                                $record
                                    ? (string) (int) $record->variants()
                                        ->join('inventory', 'product_variant.pv_id', '=', 'inventory.pv_id')
                                        ->whereNull('inventory.deleted_at')
                                        ->sum('inventory.qty_available') . ' available'
                                    : '—'
                            )
                            ->columnSpanFull()
                            ->visibleOn('edit'),
                    ]),

                // ════════════════════════════════════════════════════
                // ROW 3 RIGHT — (empty filler so layout holds)
                // ════════════════════════════════════════════════════
                Section::make('Shopify Sync')
                    ->columnSpan(1)
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextInput::make('shopify_product_id')
                            ->label('Shopify Product ID')
                            ->placeholder('gid://shopify/Product/...')
                            ->maxLength(255),
                    ]),

                // ════════════════════════════════════════════════════
                // ROW 4 LEFT — Search engine listing
                // ════════════════════════════════════════════════════
                Section::make('Search engine listing')
                    ->columnSpan(2)
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Placeholder::make('seo_preview')
                            ->label('Preview')
                            ->content(fn ($get): HtmlString => new HtmlString(
                                '<div style="border:1px solid #e5e7eb;border-radius:8px;padding:16px;background:#fff;color:#111;">'
                                . '<div style="color:#1a0dab;font-size:18px;font-weight:500;margin-bottom:2px;">'
                                . e($get('pd_name') ?: 'Page title')
                                . '</div>'
                                . '<div style="color:#006621;font-size:13px;margin-bottom:4px;">'
                                . 'uficon.com/products/' . e($get('pd_handle') ?: 'url-handle')
                                . '</div>'
                                . '<div style="color:#545454;font-size:13px;line-height:1.5;">'
                                . e(Str::limit(strip_tags($get('pd_description') ?: ''), 155))
                                . '</div>'
                                . '</div>'
                            ))
                            ->columnSpanFull(),

                        TextInput::make('pd_handle')
                            ->label('URL handle')
                            ->prefix('uficon.com/products/')
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->helperText('ตัวพิมพ์เล็ก ไม่มีช่องว่าง ใช้ - คั่น')
                            ->columnSpanFull(),
                    ]),

                // ════════════════════════════════════════════════════
                // ROW 4 RIGHT — (empty)
                // ════════════════════════════════════════════════════

            ]);
    }
}
