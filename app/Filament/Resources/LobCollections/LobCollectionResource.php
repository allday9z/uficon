<?php

namespace App\Filament\Resources\LobCollections;

use App\Filament\Resources\LobCollections\Pages\ManageLobCollections;
use App\Models\LobDisplayCollection;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class LobCollectionResource extends Resource
{
    protected static ?string $model = LobDisplayCollection::class;

    protected static string|UnitEnum|null $navigationGroup = 'คลังสินค้า';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Squares2x2;

    protected static ?string $navigationLabel = 'LOB Collections';

    protected static ?string $recordTitleAttribute = 'ldc_title';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('ข้อมูล LOB')
                ->columns(2)
                ->schema([
                    Select::make('ldc_lob')
                        ->label('LOB')
                        ->required()
                        ->options([
                            'Mac'         => 'Mac',
                            'iPhone'      => 'iPhone',
                            'iPad'        => 'iPad',
                            'Apple Watch' => 'Apple Watch',
                            'AirPods'     => 'AirPods',
                            'Apple TV'    => 'Apple TV',
                            'Accessories' => 'Accessories',
                            'Audio'       => 'Audio',
                            'HomePod'     => 'HomePod',
                        ])
                        ->searchable(),

                    TextInput::make('ldc_sub_lob')
                        ->label('Sub LOB (ตรงกับ pd_sub_lob)')
                        ->helperText('e.g. "MacBook Pro" — ต้องตรงกับค่าใน product table')
                        ->required()
                        ->maxLength(200),

                    TextInput::make('ldc_slug')
                        ->label('Slug (URL)')
                        ->required()
                        ->unique(ignorable: fn ($record) => $record)
                        ->maxLength(200)
                        ->helperText('e.g. macbook-pro — ใช้ใน /collections/{slug}')
                        ->live(onBlur: true),

                    TextInput::make('ldc_title')
                        ->label('Title (หัวข้อ LOB row)')
                        ->maxLength(500)
                        ->helperText('ถ้าว่าง ใช้ Sub LOB แทน'),
                ]),

            Section::make('Display — LOB Row')
                ->description('ข้อมูลที่แสดงในแถว product ของ LOBPage')
                ->columns(2)
                ->schema([
                    TextInput::make('ldc_badge')
                        ->label('Badge')
                        ->maxLength(100)
                        ->placeholder('ใหม่ / Sale'),

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

                    TextInput::make('ldc_button_label')
                        ->label('Button Label')
                        ->maxLength(100)
                        ->placeholder('สั่งซื้อ')
                        ->helperText('ค่า default: "สั่งซื้อ" — ใช้ "ดูสินค้า" สำหรับ accessories/custom'),

                    TextInput::make('ldc_href')
                        ->label('Link Override (URL)')
                        ->maxLength(500)
                        ->placeholder('/collections/mac-accessories')
                        ->helperText('ถ้าว่าง ใช้ /collections/{slug} อัตโนมัติ'),
                ]),

            Section::make('Display — Hero Banner (LOBPage featured)')
                ->description('แสดงเฉพาะเมื่อ is_featured = true')
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
                ->columns(3)
                ->schema([
                    TextInput::make('ldc_sort_order')
                        ->label('Sort Order')
                        ->numeric()
                        ->default(0)
                        ->helperText('น้อย = แสดงก่อน'),

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
            ])
            ->defaultSort('ldc_lob')
            ->filters([
                SelectFilter::make('ldc_lob')
                    ->label('LOB')
                    ->options([
                        'Mac'         => 'Mac',
                        'iPhone'      => 'iPhone',
                        'iPad'        => 'iPad',
                        'Apple Watch' => 'Apple Watch',
                        'AirPods'     => 'AirPods',
                        'Accessories' => 'Accessories',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLobCollections::route('/'),
        ];
    }
}
