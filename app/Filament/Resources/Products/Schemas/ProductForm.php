<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Brand;
use App\Models\OptionName;
use App\Models\ProductCollection;
use App\Models\ProductType;
use Filament\Actions\Action as FormAction;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([

                // ════════════════════════════════════════════════════════════
                // MAIN CONTENT — Tabs (2/3 width)
                // ════════════════════════════════════════════════════════════
                Tabs::make()
                    ->columnSpan(2)
                    ->tabs([

                        // ── Tab 1: Basic Info ─────────────────────────────
                        Tab::make('Basic info')
                            ->schema([
                                TextInput::make('pd_name')
                                    ->label('Title')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Apple Watch Series 10 Sport Band')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) =>
                                        $set('pd_handle', Str::slug($state))
                                    )
                                    ->columnSpanFull(),

                                Grid::make(2)->schema([
                                    TextInput::make('pd_primary_title')
                                        ->label('Primary display title')
                                        ->maxLength(500)
                                        ->placeholder('Apple Watch Series 10 Sport Band'),
                                    TextInput::make('pd_secondary_title')
                                        ->label('Secondary display title')
                                        ->maxLength(500),
                                ])->columnSpanFull(),

                                RichEditor::make('pd_description')
                                    ->label('Description')
                                    ->toolbarButtons([
                                        'bold', 'italic', 'underline', 'strike',
                                        'link', 'bulletList', 'orderedList',
                                        'h2', 'h3', 'redo', 'undo',
                                    ])
                                    ->columnSpanFull(),

                            ]),

                        // ── Tab 2: Media ──────────────────────────────────
                        Tab::make('Media')
                            ->schema([
                                Placeholder::make('media_hint')
                                    ->label('')
                                    ->content(new HtmlString(
                                        '<p class="text-sm text-gray-500">Product-level images — แสดงเสมอ เว้นแต่ variant ที่เลือกมีรูปของตัวเอง. '
                                        . 'Upload ไฟล์ หรือ paste URL แล้วกด <strong>⬇ Verify &amp; store</strong>.</p>'
                                    ))
                                    ->columnSpanFull(),

                                Repeater::make('productLevelMedia')
                                    ->relationship('productLevelMedia')
                                    ->label('')
                                    ->schema([
                                        Grid::make(12)->schema([
                                            TextInput::make('pm_src')
                                                ->label('URL / stored path')
                                                ->maxLength(1000)
                                                ->placeholder('Auto-filled after upload — or paste CaaS URL then click ⬇')
                                                ->live(onBlur: true)
                                                ->columnSpan(8)
                                                ->suffixActions([
                                                    FormAction::make('upload_file')
                                                        ->label('Upload file')
                                                        ->icon('heroicon-o-arrow-up-tray')
                                                        ->modalHeading('Upload image or video')
                                                        ->modalWidth('lg')
                                                        ->schema([
                                                            FileUpload::make('upload')
                                                                ->label('Choose image / video')
                                                                ->disk('public')
                                                                ->directory('products/media')
                                                                ->image()
                                                                ->acceptedFileTypes(['image/*', 'video/mp4', 'video/quicktime', 'video/webm'])
                                                                ->maxSize(20480)
                                                                ->required(),
                                                        ])
                                                        ->action(function (array $data, callable $set) {
                                                            if (empty($data['upload'])) return;
                                                            $set('pm_src', Storage::disk('public')->url($data['upload']));
                                                            $set('pm_type', 'image');
                                                        }),

                                                    FormAction::make('verify_store')
                                                        ->label('Verify & store URL')
                                                        ->icon('heroicon-o-arrow-down-tray')
                                                        ->action(function ($state, callable $set) {
                                                            if (empty($state) || ! filter_var($state, FILTER_VALIDATE_URL)) {
                                                                Notification::make()->warning()->title('กรอก URL ในช่องก่อน')->send();
                                                                return;
                                                            }
                                                            try {
                                                                $response = Http::timeout(20)
                                                                    ->withHeaders(['User-Agent' => 'UFicon-MediaImporter/1.0'])
                                                                    ->get($state);
                                                                if (! $response->successful()) {
                                                                    Notification::make()->danger()
                                                                        ->title('HTTP ' . $response->status())
                                                                        ->send();
                                                                    return;
                                                                }
                                                                $ext = strtolower(pathinfo(parse_url($state, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg');
                                                                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'mp4', 'mov', 'webm'];
                                                                if (! in_array($ext, $allowed)) $ext = 'jpg';
                                                                $path = 'products/media/' . Str::uuid() . '.' . $ext;
                                                                Storage::disk('public')->put($path, $response->body());
                                                                $set('pm_src', Storage::disk('public')->url($path));
                                                                Notification::make()->success()
                                                                    ->title('Stored: ' . basename($path))
                                                                    ->send();
                                                            } catch (\Exception $e) {
                                                                Notification::make()->danger()->title($e->getMessage())->send();
                                                            }
                                                        }),
                                                ]),

                                            Placeholder::make('pm_preview')
                                                ->label('Preview')
                                                ->columnSpan(4)
                                                ->content(fn (Get $get): HtmlString =>
                                                    filled($get('pm_src'))
                                                        ? new HtmlString('<img src="' . e($get('pm_src')) . '" class="h-16 w-full object-cover rounded border border-gray-200" loading="lazy" />')
                                                        : new HtmlString('<div class="h-16 rounded border border-dashed border-gray-300 flex items-center justify-center text-xs text-gray-400">no image</div>')
                                                ),
                                        ]),

                                        Grid::make(7)->schema([
                                            TextInput::make('pm_alt')
                                                ->label('Alt text')
                                                ->placeholder('e.g. Black')
                                                ->maxLength(255)
                                                ->columnSpan(3),

                                            Select::make('pm_type')
                                                ->label('Type')
                                                ->options([
                                                    'image'       => 'Image',
                                                    'video'       => 'Video',
                                                    'swatch'      => 'Color swatch',
                                                    'band_swatch' => 'Band swatch',
                                                ])
                                                ->default('image')
                                                ->native(false)
                                                ->columnSpan(3),

                                            TextInput::make('pm_position')
                                                ->label('#')
                                                ->numeric()
                                                ->default(1)
                                                ->minValue(1)
                                                ->columnSpan(1),
                                        ]),
                                    ])
                                    ->orderColumn('pm_position')
                                    ->addActionLabel('+ Add media')
                                    ->defaultItems(0)
                                    ->deletable()
                                    ->reorderable()
                                    ->columnSpanFull(),
                            ]),

                        // ── Tab 3: Options ────────────────────────────────
                        Tab::make('Options')
                            ->schema([

                                Section::make('Option dimensions')
                                    ->description('กำหนดชื่อ option และ values — Variant จะใช้ชื่อ option เหล่านี้ในการแสดงผล')
                                    ->schema([
                                        Repeater::make('options')
                                            ->relationship('options')
                                            ->label('')
                                             ->schema([
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
                                                    ->native(false)
                                                    ->columnSpanFull(),

                                                Select::make('po_display')
                                                    ->label('Display style (frontend)')
                                                    ->options([
                                                        'color_swatch' => 'Color swatch (circle)',
                                                        'band_swatch'  => 'Band swatch',
                                                        'button'       => 'Button card',
                                                        'dropdown'     => 'Dropdown',
                                                    ])
                                                    ->default('button')
                                                    ->native(false)
                                                    ->columnSpanFull(),

                                                TagsInput::make('po_values')
                                                    ->label('Allowed values')
                                                    ->placeholder('e.g. Black, Rose Gold, Starlight')
                                                    ->columnSpanFull(),
                                            ])
                                            ->addActionLabel('+ Add option')
                                            ->itemLabel(fn (array $state): ?string =>
                                                ($state['po_name'] ?? null)
                                                    ? ($state['po_name'] . (
                                                        ! empty($state['po_values'])
                                                            ? ' (' . count($state['po_values']) . ' values)'
                                                            : ' — free text'
                                                    ))
                                                    : 'Option'
                                            )
                                            ->defaultItems(0)
                                            ->orderColumn('po_position')
                                            ->reorderableWithButtons()
                                            ->columnSpanFull(),
                                    ]),

                                // Variant list → managed via sub-navigation (Variants page)
                                /* Section::make('Variant list')
                                    ->schema([
                                        Repeater::make('variants')
                                            ->relationship('variants')
                                            ->label('')
                                            ->schema(function (Get $get): array {
                                                // Read options state from parent schema
                                                $rawOptions = collect($get('options') ?? [])
                                                    ->values()
                                                    ->map(fn ($item) => [
                                                        'name'   => $item['po_name'] ?? null,
                                                        'values' => array_values(array_filter((array) ($item['po_values'] ?? []))),
                                                    ])
                                                    ->filter(fn ($item) => filled($item['name']))
                                                    ->take(7) // DB supports pv_option1-7
                                                    ->values();

                                                // Build dynamic option fields
                                                $optionFields = $rawOptions->map(function ($opt, $index) {
                                                    $fieldName = 'pv_option' . ($index + 1);
                                                    $label     = $opt['name'];
                                                    $values    = $opt['values'];

                                                    if (!empty($values)) {
                                                        return Select::make($fieldName)
                                                            ->label($label)
                                                            ->options(collect($values)->mapWithKeys(fn ($v) => [$v => $v])->all())
                                                            ->searchable()
                                                            ->native(false)
                                                            ->placeholder('Select ' . $label);
                                                    }

                                                    return TextInput::make($fieldName)
                                                        ->label($label)
                                                        ->placeholder($label . ' value');
                                                })->all();

                                                // Fallback when no options defined
                                                if (empty($optionFields)) {
                                                    $optionFields = [
                                                        TextInput::make('pv_option1')->label('Option 1')->placeholder('e.g. Black'),
                                                        TextInput::make('pv_option2')->label('Option 2')->placeholder('e.g. 256GB'),
                                                        TextInput::make('pv_option3')->label('Option 3')->placeholder('e.g. 42mm'),
                                                    ];
                                                }

                                                // Split into rows of max 4
                                                $optionGrids = collect($optionFields)
                                                    ->chunk(4)
                                                    ->map(fn ($chunk) =>
                                                        Grid::make(min($chunk->count(), 4))
                                                            ->schema($chunk->values()->all())
                                                    )
                                                    ->all();

                                                return array_merge($optionGrids, [

                                                    Grid::make(4)->schema([
                                                        TextInput::make('pv_title')
                                                            ->label('Variant title')
                                                            ->required()
                                                            ->placeholder('Black / 256GB / 42mm'),
                                                        TextInput::make('pv_mpn')
                                                            ->label('MPN')
                                                            ->placeholder('MWWF3QF/A')
                                                            ->maxLength(100)
                                                            ->live(onBlur: true)
                                                            ->afterStateUpdated(function (?string $state, callable $set, callable $get) {
                                                                if (filled($state) && blank($get('pv_handle'))) {
                                                                    $set('pv_handle', Str::slug($state));
                                                                }
                                                            }),
                                                        TextInput::make('pv_sku')
                                                            ->label('SKU')
                                                            ->maxLength(100),
                                                        TextInput::make('pv_barcode')
                                                            ->label('Barcode / GTIN')
                                                            ->maxLength(100),
                                                    ]),

                                                    Grid::make(2)->schema([
                                                        TextInput::make('pv_handle')
                                                            ->label('URL slug')
                                                            ->placeholder('iphone-17-mg6m4zp-a')
                                                            ->helperText('Auto-generated from MPN — ใช้เป็น URL path สำหรับ variant นี้')
                                                            ->maxLength(255),
                                                        TextInput::make('pv_caas_title')
                                                            ->label('CaaS full title')
                                                            ->maxLength(500),
                                                    ]),

                                                    Grid::make(2)->schema([
                                                        TextInput::make('pv_color_swatch')
                                                            ->label('Color swatch URL')
                                                            ->maxLength(1000),
                                                        TextInput::make('pv_band_swatch')
                                                            ->label('Band swatch URL')
                                                            ->maxLength(1000),
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
                                                            ->prefix('฿'),
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

                                                    Section::make('Images')
                                                        ->description('Variant-specific images — ถ้ามีรูป จะแสดงแทนรูป product เมื่อ variant นี้ถูกเลือก')
                                                        ->collapsible()
                                                        ->collapsed()
                                                        ->schema([
                                                            Repeater::make('media')
                                                                ->relationship('media')
                                                                ->label('')
                                                                ->schema([
                                                                    Grid::make(12)->schema([
                                                                        TextInput::make('pm_src')
                                                                            ->label('URL / path')
                                                                            ->maxLength(1000)
                                                                            ->placeholder('Auto-filled after upload — or paste URL then click ⬇')
                                                                            ->columnSpan(5)
                                                                            ->suffixActions([
                                                                                FormAction::make('upload_vfile')
                                                                                    ->label('Upload')
                                                                                    ->icon('heroicon-o-arrow-up-tray')
                                                                                    ->modalHeading('Upload variant image')
                                                                                    ->modalWidth('lg')
                                                                                    ->schema([
                                                                                        FileUpload::make('upload')
                                                                                            ->label('Choose image')
                                                                                            ->disk('public')
                                                                                            ->directory('products/variants')
                                                                                            ->image()
                                                                                            ->acceptedFileTypes(['image/*', 'video/mp4', 'video/quicktime'])
                                                                                            ->maxSize(20480)
                                                                                            ->required(),
                                                                                    ])
                                                                                    ->action(function (array $data, callable $set) {
                                                                                        if (empty($data['upload'])) return;
                                                                                        $set('pm_src', Storage::disk('public')->url($data['upload']));
                                                                                        $set('pm_type', 'image');
                                                                                    }),

                                                                                FormAction::make('verify_vstore')
                                                                                    ->label('Verify & store')
                                                                                    ->icon('heroicon-o-arrow-down-tray')
                                                                                    ->action(function ($state, callable $set) {
                                                                                        if (empty($state) || ! filter_var($state, FILTER_VALIDATE_URL)) {
                                                                                            Notification::make()->warning()->title('กรอก URL ก่อน')->send();
                                                                                            return;
                                                                                        }
                                                                                        try {
                                                                                            $response = Http::timeout(20)
                                                                                                ->withHeaders(['User-Agent' => 'UFicon-MediaImporter/1.0'])
                                                                                                ->get($state);
                                                                                            if (! $response->successful()) {
                                                                                                Notification::make()->danger()->title('HTTP ' . $response->status())->send();
                                                                                                return;
                                                                                            }
                                                                                            $ext = strtolower(pathinfo(parse_url($state, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg');
                                                                                            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'mp4', 'mov', 'webm'];
                                                                                            if (! in_array($ext, $allowed)) $ext = 'jpg';
                                                                                            $path = 'products/variants/' . Str::uuid() . '.' . $ext;
                                                                                            Storage::disk('public')->put($path, $response->body());
                                                                                            $set('pm_src', Storage::disk('public')->url($path));
                                                                                            Notification::make()->success()->title('Stored: ' . basename($path))->send();
                                                                                        } catch (\Exception $e) {
                                                                                            Notification::make()->danger()->title($e->getMessage())->send();
                                                                                        }
                                                                                    }),
                                                                            ]),
                                                                        TextInput::make('pm_alt')
                                                                            ->label('Alt')
                                                                            ->maxLength(255)
                                                                            ->columnSpan(3),
                                                                        Select::make('pm_type')
                                                                            ->label('Type')
                                                                            ->options([
                                                                                'image'      => 'Image',
                                                                                'video'      => 'Video',
                                                                                'swatch'     => 'Color swatch',
                                                                                'band_swatch'=> 'Band swatch',
                                                                            ])
                                                                            ->default('image')
                                                                            ->native(false)
                                                                            ->columnSpan(3),
                                                                        TextInput::make('pm_position')
                                                                            ->label('#')
                                                                            ->numeric()
                                                                            ->default(1)
                                                                            ->minValue(1)
                                                                            ->columnSpan(1),
                                                                    ]),
                                                                ])
                                                                ->orderColumn('pm_position')
                                                                ->reorderableWithDragAndDrop()
                                                                ->addActionLabel('+ Add image')
                                                                ->defaultItems(0)
                                                                ->deletable()
                                                                ->columnSpanFull(),
                                                        ]),
                                                ]);
                                            })
                                            ->addActionLabel('+ Add variant')
                                            ->itemLabel(fn (array $state): string =>
                                                collect(range(1, 7))
                                                    ->map(fn ($i) => $state['pv_option' . $i] ?? null)
                                                    ->filter()
                                                    ->implode(' / ')
                                                ?: ($state['pv_mpn'] ?? $state['pv_title'] ?? 'New variant')
                                            )
                                            ->collapsed()
                                            ->reorderableWithButtons()
                                            ->defaultItems(1)
                                            ->columnSpanFull(),

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
                                    ]), */
                            ]),

                        // ── Tab 4: Packaging & Warranty ───────────────────
                        Tab::make('Packaging & Warranty')
                            ->schema([

                                Section::make('In the box')
                                    ->description('Items included in the box — CaaS: In the box 1–4')
                                    ->schema([
                                        Repeater::make('inbox')
                                            ->relationship('inbox')
                                            ->label('')
                                            ->schema([
                                                Grid::make(12)->schema([
                                                    TextInput::make('pib_text')
                                                        ->label('Item name')
                                                        ->required()
                                                        ->maxLength(500)
                                                        ->placeholder('1m Magnetic Charging Cable')
                                                        ->columnSpan(8),

                                                    TextInput::make('pib_image')
                                                        ->label('Image URL')
                                                        ->maxLength(500)
                                                        ->placeholder('https://...')
                                                        ->live(onBlur: true)
                                                        ->columnSpan(4),
                                                ]),

                                                Placeholder::make('pib_image_preview')
                                                    ->label('')
                                                    ->content(fn (Get $get): HtmlString =>
                                                        filled($get('pib_image'))
                                                            ? new HtmlString('<img src="' . e($get('pib_image')) . '" class="h-12 w-12 object-contain rounded border border-gray-200" loading="lazy" />')
                                                            : new HtmlString('')
                                                    )
                                                    ->columnSpanFull(),
                                            ])
                                            ->addActionLabel('+ Add item')
                                            ->maxItems(4)
                                            ->defaultItems(0)
                                            ->orderColumn('pib_position')
                                            ->reorderableWithButtons()
                                            ->columnSpanFull(),
                                    ]),

                                Section::make("Manufacturer's Warranty")
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Textarea::make('pd_warranty_parts')
                                                ->label('Parts warranty')
                                                ->rows(3)
                                                ->placeholder('Apple One (1) Year Limited Warranty'),
                                            Textarea::make('pd_warranty_labor')
                                                ->label('Labor warranty')
                                                ->rows(3)
                                                ->placeholder('Apple One (1) Year Limited Warranty'),
                                        ]),
                                    ]),
                            ]),

                        // ── Tab 5: Content ───────────────────────────────
                        Tab::make('Content')
                            ->schema([

                                Section::make('Features')
                                    ->description('Auto-populated from CaaS import — แก้ไขได้ถ้าต้องการ override')
                                    ->schema([
                                        RichEditor::make('pd_features')
                                            ->label('')
                                            ->toolbarButtons([
                                                'bold', 'italic',
                                                'bulletList', 'orderedList',
                                                'h2', 'h3',
                                                'redo', 'undo',
                                            ])
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Content sections')
                                    ->description('Accordion sections ที่แสดงใน product page — Financing options, Technical services, Trade-in, Shipping ฯลฯ (แก้ไขด้วยตนเอง)')
                                    ->schema([
                                        Repeater::make('pd_content_sections')
                                            ->hiddenLabel()
                                            ->schema([
                                                TextInput::make('title')
                                                    ->label('Section title')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->placeholder('Financing options')
                                                    ->columnSpanFull(),

                                                RichEditor::make('body')
                                                    ->label('Content (HTML)')
                                                    ->toolbarButtons([
                                                        'bold', 'italic', 'underline',
                                                        'link', 'bulletList', 'orderedList',
                                                        'redo', 'undo',
                                                    ])
                                                    ->columnSpanFull(),

                                                Grid::make(2)->schema([
                                                    TextInput::make('link_text')
                                                        ->label('Link text')
                                                        ->maxLength(255)
                                                        ->placeholder('Financing'),

                                                    TextInput::make('link_url')
                                                        ->label('Link URL')
                                                        ->maxLength(500)
                                                        ->placeholder('/financing'),
                                                ]),
                                            ])
                                            ->addActionLabel('+ Add section')
                                            ->defaultItems(0)
                                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                                            ->collapsible()
                                            ->reorderableWithDragAndDrop()
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Overview')
                                    ->description('Auto-generated จาก CaaS import — Description + Features + In The Box + Warranty')
                                    ->schema([
                                        Placeholder::make('pd_overview_preview')
                                            ->label('')
                                            ->content(fn ($record): HtmlString =>
                                                $record && filled($record->pd_overview)
                                                    ? new HtmlString('<div class="prose max-w-none text-sm py-2">' . $record->pd_overview . '</div>')
                                                    : new HtmlString('<div class="text-gray-400 italic text-sm py-3">ยังไม่มีข้อมูล — run CaaS import เพื่อ populate อัตโนมัติ</div>')
                                            )
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // ── Tab 6: SEO ────────────────────────────────────
                        Tab::make('SEO')
                            ->schema([
                                Placeholder::make('seo_preview')
                                    ->label('Search result preview')
                                    ->content(fn ($get): HtmlString => new HtmlString(
                                        '<div style="border:1px solid #e5e7eb;border-radius:8px;padding:20px;background:#fff;color:#111;">'
                                        . '<div style="color:#1a0dab;font-size:18px;font-weight:500;margin-bottom:4px;">'
                                        . e($get('pd_meta_title') ?: $get('pd_name') ?: 'Page title')
                                        . '</div>'
                                        . '<div style="color:#006621;font-size:13px;margin-bottom:6px;">'
                                        . 'uficon.com/products/' . e($get('pd_handle') ?: 'url-handle')
                                        . '</div>'
                                        . '<div style="color:#545454;font-size:14px;line-height:1.6;">'
                                        . e(Str::limit($get('pd_meta_desc') ?: strip_tags($get('pd_description') ?: 'Add a description to see a preview.'), 155))
                                        . '</div>'
                                        . '</div>'
                                    ))
                                    ->columnSpanFull(),

                                TextInput::make('pd_handle')
                                    ->label('URL handle')
                                    ->prefix('uficon.com/products/')
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->helperText('Lowercase, hyphens only — auto-generated from title')
                                    ->columnSpanFull(),

                                TextInput::make('pd_meta_title')
                                    ->label('Meta title')
                                    ->maxLength(255)
                                    ->placeholder('Leave blank to use product title')
                                    ->helperText('Recommended: 50–60 characters')
                                    ->live(onBlur: true)
                                    ->columnSpanFull(),

                                Textarea::make('pd_meta_desc')
                                    ->label('Meta description')
                                    ->rows(3)
                                    ->maxLength(300)
                                    ->placeholder('Leave blank to use product description')
                                    ->helperText('Recommended: 140–160 characters')
                                    ->live(onBlur: true)
                                    ->columnSpanFull(),

                                TextInput::make('pd_meta_image')
                                    ->label('OG image URL')
                                    ->maxLength(500)
                                    ->placeholder('https://... (1200×630 px for best results)')
                                    ->helperText('Social share image — leave blank to use first product image')
                                    ->columnSpanFull(),
                            ]),

                        // ── Tab 7: PDP Builder ─────────────────────────────
                        Tab::make('PDP Builder')
                            ->schema([
                                Builder::make('pdp_content')
                                    ->hiddenLabel()
                                    ->collapsible()
                                    ->cloneable(false)
                                    ->reorderableWithDragAndDrop(false)
                                    ->columnSpanFull()
                                    ->blocks([

                                        // ── Block: Page Meta ──────────────
                                        Block::make('page_meta')
                                            ->label('Page Settings')
                                            ->schema([
                                                Grid::make(3)->schema([
                                                    TextInput::make('tagline')
                                                        ->label('Tagline')
                                                        ->placeholder('Just the way you want it.')
                                                        ->maxLength(255),
                                                    TextInput::make('size')
                                                        ->label('Size label')
                                                        ->placeholder('14')
                                                        ->maxLength(50),
                                                    TextInput::make('currency')
                                                        ->label('Currency')
                                                        ->placeholder('THB')
                                                        ->default('THB')
                                                        ->maxLength(10),
                                                ]),
                                                Grid::make(3)->schema([
                                                    TextInput::make('monthly_term')
                                                        ->label('Monthly term (months)')
                                                        ->numeric()
                                                        ->default(10)
                                                        ->minValue(1),
                                                    TextInput::make('apple_care_price')
                                                        ->label('AppleCare+ price')
                                                        ->numeric()
                                                        ->prefix('฿')
                                                        ->placeholder('9900'),
                                                    TextInput::make('default_color')
                                                        ->label('Default color ID')
                                                        ->placeholder('space-black')
                                                        ->maxLength(100),
                                                ]),
                                            ]),

                                        // ── Block: Processor Options ──────
                                        Block::make('configurator_processors')
                                            ->label('Processor Options')
                                            ->schema([
                                                Repeater::make('items')
                                                    ->hiddenLabel()
                                                    ->schema([
                                                        Grid::make(2)->schema([
                                                            TextInput::make('id')
                                                                ->label('ID (slug)')
                                                                ->placeholder('m5-10')
                                                                ->required()
                                                                ->maxLength(100),
                                                            TextInput::make('price_add')
                                                                ->label('Price add (฿)')
                                                                ->numeric()
                                                                ->default(0)
                                                                ->prefix('฿'),
                                                        ]),
                                                        Grid::make(2)->schema([
                                                            TextInput::make('label')
                                                                ->label('Label')
                                                                ->placeholder('MacBook Pro M5')
                                                                ->required()
                                                                ->maxLength(255),
                                                            TextInput::make('sublabel')
                                                                ->label('Sublabel')
                                                                ->placeholder('10-core CPU and 10-core GPU')
                                                                ->maxLength(255),
                                                        ]),
                                                    ])
                                                    ->addActionLabel('+ Add processor')
                                                    ->defaultItems(0)
                                                    ->itemLabel(fn (array $state): ?string =>
                                                        ($state['label'] ?? null)
                                                            ? "{$state['label']} (+฿" . number_format((int)($state['price_add'] ?? 0)) . ")"
                                                            : null
                                                    )
                                                    ->collapsible()
                                                    ->reorderableWithDragAndDrop(),
                                            ]),

                                        // ── Block: Memory Options ─────────
                                        Block::make('configurator_memory')
                                            ->label('Memory Options')
                                            ->schema([
                                                Repeater::make('items')
                                                    ->hiddenLabel()
                                                    ->schema([
                                                        Grid::make(3)->schema([
                                                            TextInput::make('id')
                                                                ->label('ID (slug)')
                                                                ->placeholder('24gb')
                                                                ->required()
                                                                ->maxLength(100),
                                                            TextInput::make('label')
                                                                ->label('Label')
                                                                ->placeholder('24GB')
                                                                ->required()
                                                                ->maxLength(100),
                                                            TextInput::make('price_add')
                                                                ->label('Price add (฿)')
                                                                ->numeric()
                                                                ->default(0)
                                                                ->prefix('฿'),
                                                        ]),
                                                    ])
                                                    ->addActionLabel('+ Add memory tier')
                                                    ->defaultItems(0)
                                                    ->itemLabel(fn (array $state): ?string =>
                                                        ($state['label'] ?? null)
                                                            ? "{$state['label']} (+฿" . number_format((int)($state['price_add'] ?? 0)) . ")"
                                                            : null
                                                    )
                                                    ->collapsible()
                                                    ->reorderableWithDragAndDrop(),
                                            ]),

                                        // ── Block: Storage Options ────────
                                        Block::make('configurator_storage')
                                            ->label('Storage Options')
                                            ->schema([
                                                Repeater::make('items')
                                                    ->hiddenLabel()
                                                    ->schema([
                                                        Grid::make(3)->schema([
                                                            TextInput::make('id')
                                                                ->label('ID (slug)')
                                                                ->placeholder('512gb')
                                                                ->required()
                                                                ->maxLength(100),
                                                            TextInput::make('label')
                                                                ->label('Label')
                                                                ->placeholder('512GB')
                                                                ->required()
                                                                ->maxLength(100),
                                                            TextInput::make('price_add')
                                                                ->label('Price add (฿)')
                                                                ->numeric()
                                                                ->default(0)
                                                                ->prefix('฿'),
                                                        ]),
                                                    ])
                                                    ->addActionLabel('+ Add storage tier')
                                                    ->defaultItems(0)
                                                    ->itemLabel(fn (array $state): ?string =>
                                                        ($state['label'] ?? null)
                                                            ? "{$state['label']} (+฿" . number_format((int)($state['price_add'] ?? 0)) . ")"
                                                            : null
                                                    )
                                                    ->collapsible()
                                                    ->reorderableWithDragAndDrop(),
                                            ]),

                                        // ── Block: Tech Specs ─────────────
                                        Block::make('specs_table')
                                            ->label('Tech Specs (PDP table)')
                                            ->schema([
                                                Repeater::make('items')
                                                    ->hiddenLabel()
                                                    ->schema([
                                                        Grid::make(2)->schema([
                                                            TextInput::make('label')
                                                                ->label('Spec label')
                                                                ->placeholder('จอภาพ')
                                                                ->required()
                                                                ->maxLength(255),
                                                            TextInput::make('value')
                                                                ->label('Spec value')
                                                                ->placeholder('14.2 นิ้ว Liquid Retina XDR')
                                                                ->required()
                                                                ->maxLength(500),
                                                        ]),
                                                    ])
                                                    ->addActionLabel('+ Add spec row')
                                                    ->defaultItems(0)
                                                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                                                    ->collapsible()
                                                    ->reorderableWithDragAndDrop(),
                                            ]),

                                        // ── Block: Bundle Items ───────────
                                        Block::make('bundle_items')
                                            ->label('Bundle Items (Add-ons / Accessories)')
                                            ->schema([
                                                Repeater::make('items')
                                                    ->hiddenLabel()
                                                    ->schema([
                                                        Grid::make(2)->schema([
                                                            TextInput::make('id')
                                                                ->label('ID (slug)')
                                                                ->placeholder('magic-mouse-2')
                                                                ->required()
                                                                ->maxLength(100),
                                                            TextInput::make('name')
                                                                ->label('Display name')
                                                                ->placeholder('Magic Mouse 2')
                                                                ->required()
                                                                ->maxLength(255),
                                                        ]),
                                                        Grid::make(2)->schema([
                                                            TextInput::make('price')
                                                                ->label('Price (฿)')
                                                                ->numeric()
                                                                ->required()
                                                                ->prefix('฿'),
                                                            TextInput::make('image_src')
                                                                ->label('Image URL')
                                                                ->placeholder('https://...')
                                                                ->maxLength(1000),
                                                        ]),
                                                    ])
                                                    ->addActionLabel('+ Add bundle item')
                                                    ->defaultItems(0)
                                                    ->itemLabel(fn (array $state): ?string =>
                                                        isset($state['name'], $state['price'])
                                                            ? "{$state['name']} — ฿" . number_format((int)$state['price'])
                                                            : null
                                                    )
                                                    ->collapsible()
                                                    ->reorderableWithDragAndDrop(),
                                            ]),

                                    ]),
                            ]),

                    ]),

                // ════════════════════════════════════════════════════════════
                // SIDEBAR — Status + Organization (1/3 width)
                // ════════════════════════════════════════════════════════════
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

                                Select::make('pd_badge')
                                    ->label('Badge')
                                    ->options([
                                        'new'       => '🆕 New',
                                        'hot'       => '🔥 Hot',
                                        'sale'      => '💸 Sale',
                                        'pre_order' => '📦 Pre-order',
                                        'best_seller' => '⭐ Best Seller',
                                    ])
                                    ->nullable()
                                    ->native(false)
                                    ->placeholder('No badge'),

                                Select::make('pd_template_type')
                                    ->label('Frontend template')
                                    ->options([
                                        'simple'  => 'Simple (iPhone/iPad/Watch/AirPods)',
                                        'full'     => 'Full (MacBook/Mac)',
                                    ])
                                    ->default('simple')
                                    ->required()
                                    ->native(false)
                                    ->helperText('Controls how the product page renders on the frontend'),
                            ]),

                        Section::make('Publishing')
                            ->schema([
                                DateTimePicker::make('published_at')
                                    ->label('Published at')
                                    ->native(false)
                                    ->default(now()),
                            ]),

                        Section::make('Product organization')
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

                                Select::make('pcol_id')
                                    ->label('Collection (CaaS)')
                                    ->options(fn () => ProductCollection::orderBy('pcol_title')
                                        ->pluck('pcol_title', 'pcol_id'))
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->helperText('Base product collection handle จาก CaaS'),

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

                                Grid::make(2)->schema([
                                    TextInput::make('pd_lob')
                                        ->label('LOB')
                                        ->maxLength(100)
                                        ->placeholder('iPhone'),
                                    TextInput::make('pd_sub_lob')
                                        ->label('Sub LOB')
                                        ->maxLength(150)
                                        ->placeholder('iPhone 16'),
                                ]),

                                TextInput::make('pd_base_name')
                                    ->label('Base product name')
                                    ->maxLength(255)
                                    ->placeholder('iPhone 16'),

                                TagsInput::make('tag_list')
                                    ->label('Tags')
                                    ->placeholder('Add tag...')
                                    ->separator(','),
                            ]),

                        Section::make('Shopify Sync')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                TextInput::make('shopify_product_id')
                                    ->label('Shopify Product ID')
                                    ->placeholder('gid://shopify/Product/...')
                                    ->maxLength(255),
                            ]),
                    ]),

            ]);
    }
}
