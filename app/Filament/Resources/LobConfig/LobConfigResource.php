<?php

namespace App\Filament\Resources\LobConfig;

use App\Filament\Resources\LobConfig\Pages\ManageLobConfigs;
use App\Models\LobConfig;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class LobConfigResource extends Resource
{
    protected static ?string $model = LobConfig::class;

    protected static string|UnitEnum|null $navigationGroup = 'คลังสินค้า';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'LOB Header Config';

    protected static ?string $recordTitleAttribute = 'lc_lob';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('LOB')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make('lc_lob')
                        ->label('LOB (ชื่อจริงใน DB)')
                        ->required()
                        ->unique(ignorable: fn ($record) => $record)
                        ->options(fn () => Product::whereNotNull('pd_lob')
                            ->distinct()->orderBy('pd_lob')->pluck('pd_lob', 'pd_lob')->toArray()
                        )
                        ->searchable(),

                    TextInput::make('lc_url_slug')
                        ->label('URL Slug (alias)')
                        ->unique(ignorable: fn ($record) => $record)
                        ->maxLength(100)
                        ->placeholder('music')
                        ->helperText('slug ที่ใช้ใน /pages/view-all-{slug} ถ้าต่างจากชื่อ LOB เช่น AirPods → music'),
                ]),

            Section::make('Header Banner')
                ->description('แบนเนอร์ใต้ FamilyStripe — คลิกเปิด Compare modal')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    FileUpload::make('lc_header_image_desktop')
                        ->label('Desktop Image')
                        ->image()
                        ->disk('public')
                        ->directory('lob-banners')
                        ->visibility('public')
                        ->imagePreviewHeight('120')
                        ->maxSize(10240)
                        ->acceptedFileTypes(['image/jpeg','image/png','image/webp','image/gif'])
                        ->helperText('แบนเนอร์ Desktop (jpg/png/webp, max 10MB)'),

                    FileUpload::make('lc_header_image_mobile')
                        ->label('Mobile Image')
                        ->image()
                        ->disk('public')
                        ->directory('lob-banners')
                        ->visibility('public')
                        ->imagePreviewHeight('120')
                        ->maxSize(10240)
                        ->acceptedFileTypes(['image/jpeg','image/png','image/webp','image/gif'])
                        ->helperText('แบนเนอร์ Mobile (แนวตั้ง)'),

                    Select::make('lc_banner_action')
                        ->label('เมื่อคลิกแบนเนอร์')
                        ->columnSpanFull()
                        ->options([
                            'compare' => 'เปิด Compare Modal',
                            'none'    => 'ไม่ทำอะไร',
                        ])
                        ->default('compare'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lc_lob')
                    ->label('LOB')
                    ->badge()
                    ->sortable(),

                TextColumn::make('lc_url_slug')
                    ->label('URL Slug')
                    ->fontFamily('mono')
                    ->color('gray')
                    ->placeholder('—'),

                ImageColumn::make('lc_header_image_desktop')
                    ->label('Desktop')
                    ->disk('public')
                    ->height(48)
                    ->defaultImageUrl('https://placehold.co/120x48/f3f4f6/9ca3af?text=—'),

                TextColumn::make('lc_banner_action')
                    ->label('Action')
                    ->badge()
                    ->color(fn ($state) => $state === 'compare' ? 'success' : 'gray'),
            ])
            ->defaultSort('lc_lob')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLobConfigs::route('/'),
        ];
    }
}
