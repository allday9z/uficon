<?php

namespace App\Filament\Resources\LobCollections;

use App\Filament\Resources\LobCollections\Pages\ManageLobCollections;
use App\Models\LobDisplayCollection;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Storage;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class LobCollectionResource extends Resource
{
    protected static ?string $model = LobDisplayCollection::class;

    protected static string|UnitEnum|null $navigationGroup = 'คลังสินค้า';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'LOB Collections';

    protected static ?string $recordTitleAttribute = 'ldc_title';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('ข้อมูล LOB')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make('ldc_lob')
                        ->label('LOB')
                        ->required()
                        ->options(fn () => Product::whereNotNull('pd_lob')
                            ->distinct()->orderBy('pd_lob')->pluck('pd_lob', 'pd_lob')->toArray()
                        )
                        ->searchable()
                        ->live(),

                    Select::make('ldc_sub_lob')
                        ->label('Sub LOB')
                        ->required()
                        ->helperText('ต้องตรงกับค่า pd_sub_lob ใน product table')
                        ->options(fn (Get $get) => Product::whereNotNull('pd_sub_lob')
                            ->when($get('ldc_lob'), fn ($q, $lob) => $q->where('pd_lob', $lob))
                            ->distinct()->orderBy('pd_sub_lob')->pluck('pd_sub_lob', 'pd_sub_lob')
                            ->toArray()
                        )
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set) {
                            if ($state) {
                                $set('ldc_slug', Str::slug($state) ?: 'group-' . substr(md5($state), 0, 8));
                                $set('ldc_title', $state);
                            }
                        }),

                    TextInput::make('ldc_slug')
                        ->label('Slug (URL)')
                        ->required()
                        ->unique(ignorable: fn ($record) => $record)
                        ->maxLength(200)
                        ->helperText('ใช้ใน /collections/{slug} — auto-fill จาก Sub LOB'),

                    TextInput::make('ldc_title')
                        ->label('Title (หัวข้อ LOB row)')
                        ->maxLength(500)
                        ->helperText('ถ้าว่าง ใช้ Sub LOB แทน'),
                ]),

            Section::make('Display — LOB Row')
                ->description('ข้อมูลที่แสดงในแถว product ของ LOBPage')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make('ldc_badge')
                        ->label('Badge')
                        ->options([
                            'ใหม่'      => 'ใหม่',
                            'Sale'      => 'Sale',
                            'Pre-Order' => 'Pre-Order',
                            'In-Store'  => 'In-Store',
                        ])
                        ->searchable()
                        ->placeholder('ไม่มี Badge'),

                    TextInput::make('ldc_tagline')
                        ->label('Tagline (บรรทัดแรก)')
                        ->maxLength(500)
                        ->placeholder('พื้นที่เล็ก พลังงานมหาศาล')
                        ->helperText('แสดงใต้ title ในแถว LOB'),

                    TextInput::make('ldc_image_src')
                        ->label('รูป LOB Row (URL)')
                        ->maxLength(2000)
                        ->columnSpanFull()
                        ->placeholder('https://cdn.../mac-mini.png')
                        ->helperText('รูปด้านขวาของแถว — ถ้าว่าง ใช้รูปจาก product แรกใน group'),

                    Select::make('ldc_button_label')
                        ->label('Button Label')
                        ->options([
                            'สั่งซื้อ'  => 'สั่งซื้อ (default)',
                            'ดูสินค้า'  => 'ดูสินค้า (accessories)',
                            'เพิ่มเติม' => 'เพิ่มเติม',
                            'สั่งที่นี่' => 'สั่งที่นี่',
                        ])
                        ->placeholder('สั่งซื้อ (default)')
                        ->helperText('ค่า default: "สั่งซื้อ" — Accessories ควรใช้ "ดูสินค้า"'),

                    TextInput::make('ldc_href')
                        ->label('Link Override (URL)')
                        ->maxLength(500)
                        ->placeholder('/collections/mac-accessories')
                        ->helperText('ถ้าว่าง ใช้ /collections/{slug} อัตโนมัติ'),
                ]),

            Section::make('Display — Hero Banner (LOBPage featured)')
                ->description('แสดงเฉพาะเมื่อ is_featured = true')
                ->columnSpanFull()
                ->collapsed()
                ->columns(2)
                ->schema([
                    TextInput::make('ldc_description')
                        ->label('Description (LOB Hero body)')
                        ->maxLength(1000)
                        ->placeholder('ความเร็วอยู่ในสายเลือด'),

                    TextInput::make('ldc_hero_image')
                        ->label('Hero Banner Image (URL)')
                        ->maxLength(2000)
                        ->placeholder('https://cdn.../banner.jpg'),

                    TextInput::make('ldc_hero_detail_href')
                        ->label('Hero "ดูรายละเอียดสินค้า" URL')
                        ->maxLength(500),

                    TextInput::make('ldc_hero_buy_href')
                        ->label('Hero "สั่งซื้อ" URL')
                        ->maxLength(500),
                ]),

            Section::make('FamilyStripe Thumbnail')
                ->columnSpanFull()
                ->collapsed()
                ->schema([
                    // ── actual stored field — Hidden so no option validation ──
                    \Filament\Forms\Components\Hidden::make('ldc_stripe_image'),

                    // ① pick from gallery (dehydrated:false — sets Hidden field above)
                    Select::make('ldc_gallery_pick')
                        ->label('① เลือกรูปจาก Gallery สี')
                        ->columnSpanFull()
                        ->searchable()
                        ->dehydrated(false)
                        ->placeholder('ว่าง = ใช้รูปแรกจาก defaultColor อัตโนมัติ')
                        ->helperText('เลือกรูปจาก gallery สีของ product ใน sub-lob นี้')
                        ->options(function (Get $get) {
                            $subLob = $get('ldc_sub_lob');
                            if (! $subLob) return [];
                            return \App\Models\ProductMedia::query()
                                ->join('product_gallery as g', 'g.pg_id', '=', 'product_media.pg_id')
                                ->join('product as p', 'p.pd_id', '=', 'product_media.pd_id')
                                ->where('p.pd_sub_lob', $subLob)
                                ->where('product_media.pm_type', 'image')
                                ->orderBy('p.pd_primary_title')
                                ->orderBy('g.pg_name')
                                ->orderBy('product_media.pm_position')
                                ->limit(150)
                                ->get(['product_media.pm_src', 'product_media.pm_position', 'g.pg_name', 'p.pd_primary_title'])
                                ->mapWithKeys(fn ($m) => [
                                    $m->pm_src => "[{$m->pd_primary_title}] {$m->pg_name} — รูป #{$m->pm_position}",
                                ])
                                ->toArray();
                        })
                        ->live()
                        ->afterStateUpdated(fn ($state, callable $set) => $set('ldc_stripe_image', $state)),

                    // ② upload — NOT dehydrated(false) so Filament moves file on save
                    // path → URL conversion handled in mutateFormDataBefore* on page class
                    FileUpload::make('ldc_stripe_image_file')
                        ->label('② อัพโหลดรูปเอง')
                        ->columnSpanFull()
                        ->disk('public')
                        ->directory('lob/stripe-thumbnails')
                        ->image()
                        ->imagePreviewHeight('100')
                        ->maxSize(5120)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif'])
                        ->helperText('JPG · PNG · WebP · AVIF — max 5MB — จะ override ①'),

                    // ③ CDN URL (dehydrated:false — sets Hidden field above)
                    TextInput::make('ldc_stripe_image_url')
                        ->label('③ หรือ ใส่ CDN URL เอง')
                        ->maxLength(2000)
                        ->columnSpanFull()
                        ->placeholder('https://cdn.../stripe-thumb.png')
                        ->helperText('จะ override ① และ ②')
                        ->dehydrated(false)
                        ->live()
                        ->afterStateUpdated(fn ($state, callable $set) => $set('ldc_stripe_image', $state ?: null)),

                    \Filament\Forms\Components\Placeholder::make('stripe_preview')
                        ->label('Preview รูปปัจจุบัน')
                        ->columnSpanFull()
                        ->content(function (Get $get): \Illuminate\Support\HtmlString {
                            $src = $get('ldc_stripe_image') ?? '';
                            if (! $src) return new \Illuminate\Support\HtmlString('<p style="font-size:12px;color:#6b7280;">ยังไม่ได้เลือก</p>');
                            $filename = basename(parse_url($src, PHP_URL_PATH));
                            return new \Illuminate\Support\HtmlString('
<div style="display:flex;align-items:center;gap:14px;padding:8px 12px;background:#1c1c1e;border:1px solid #374151;border-radius:10px;">
  <img src="' . e($src) . '" style="height:100px;width:auto;max-width:180px;border-radius:8px;object-fit:contain;background:#2c2c2e;flex-shrink:0;" />
  <div style="font-size:12px;color:#9ca3af;word-break:break-all;">' . e($filename) . '</div>
</div>');
                        }),
                ]),

            Section::make('การแสดงผล')
                ->columnSpanFull()
                ->columns(4)
                ->schema([
                    TextInput::make('ldc_sort_order')
                        ->label('Sort Order')
                        ->numeric()
                        ->default(0)
                        ->helperText('น้อย = แสดงก่อน'),

                    DatePicker::make('ldc_sale_date')
                        ->label('วันที่วางขาย')
                        ->helperText('ใช้สำหรับเรียงลำดับ ใหม่→เก่า')
                        ->displayFormat('d/m/Y')
                        ->native(false),

                    Toggle::make('ldc_is_featured')
                        ->label('Featured Hero')
                        ->helperText('แสดงเป็น hero banner บน LOBPage (1 ต่อ LOB)'),

                    Toggle::make('ldc_is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('ซ่อน/แสดงใน frontend'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ldc_lob')
                    ->label('LOB')
                    ->badge()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('ldc_sub_lob')
                    ->label('Sub LOB')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('ldc_slug')
                    ->label('Slug')
                    ->fontFamily('mono')
                    ->color('gray')
                    ->searchable(),

                TextColumn::make('ldc_title')
                    ->label('Title')
                    ->searchable(),

                TextColumn::make('ldc_badge')
                    ->label('Badge')
                    ->badge()
                    ->color('warning'),

                IconColumn::make('ldc_is_featured')
                    ->label('Hero')
                    ->boolean()
                    ->trueIcon(Heroicon::Star)
                    ->falseIcon(Heroicon::Minus),

                IconColumn::make('ldc_is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('ldc_sort_order')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('ldc_sale_date')
                    ->label('วันวางขาย')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->defaultSort('ldc_sort_order')
            ->reorderable('ldc_sort_order')
            ->filters([
                SelectFilter::make('ldc_lob')
                    ->label('LOB')
                    ->options(fn () => LobDisplayCollection::whereNotNull('ldc_lob')
                        ->distinct()->orderBy('ldc_lob')->pluck('ldc_lob', 'ldc_lob')->toArray()
                    )
                    ->searchable(),

                TernaryFilter::make('ldc_is_active')
                    ->label('Active')
                    ->placeholder('ทั้งหมด')
                    ->trueLabel('Active เท่านั้น')
                    ->falseLabel('Inactive เท่านั้น'),

                TernaryFilter::make('ldc_is_featured')
                    ->label('Featured')
                    ->placeholder('ทั้งหมด')
                    ->trueLabel('Featured เท่านั้น')
                    ->falseLabel('ไม่ featured'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->groupedBulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLobCollections::route('/'),
        ];
    }
}
