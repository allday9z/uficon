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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                    TextInput::make('ldc_stripe_image')
                        ->label('FamilyStripe Chip Image (URL)')
                        ->maxLength(2000)
                        ->columnSpanFull()
                        ->placeholder('https://cdn.../stripe-thumb.png')
                        ->helperText('รูปใน chip แถบบนสุดของ LOBPage'),
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
