<?php

namespace App\Filament\Resources\LobConfig;

use App\Filament\Resources\LobConfig\Pages\ManageLobConfigs;
use App\Models\LobConfig;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
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
                ->schema([
                    Select::make('lc_lob')
                        ->label('LOB')
                        ->required()
                        ->unique(ignorable: fn ($record) => $record)
                        ->options(fn () => Product::whereNotNull('pd_lob')
                            ->distinct()->orderBy('pd_lob')->pluck('pd_lob', 'pd_lob')->toArray()
                        )
                        ->searchable(),
                ]),

            Section::make('Header Banner')
                ->description('แบนเนอร์ใต้ FamilyStripe — "iPhone ใดที่เหมาะกับคุณ?"')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('lc_header_image_desktop')
                        ->label('Desktop Image (URL)')
                        ->maxLength(2000)
                        ->columnSpanFull()
                        ->placeholder('https://cdn.../which-iphone-desktop.webp'),

                    TextInput::make('lc_header_image_mobile')
                        ->label('Mobile Image (URL)')
                        ->maxLength(2000)
                        ->columnSpanFull()
                        ->placeholder('https://cdn.../which-iphone-mobile.webp'),

                    TextInput::make('lc_header_text')
                        ->label('Heading Text')
                        ->maxLength(500)
                        ->placeholder('iPhone ใดที่เหมาะกับคุณ?'),

                    TextInput::make('lc_header_link')
                        ->label('Banner Click Link (ทั้งแบนเนอร์)')
                        ->maxLength(500)
                        ->placeholder('/iphone/compare'),

                    TextInput::make('lc_header_btn_label')
                        ->label('Button Label')
                        ->maxLength(100)
                        ->placeholder('เปรียบเทียบทุกรุ่น'),

                    TextInput::make('lc_header_btn_href')
                        ->label('Button URL')
                        ->maxLength(500)
                        ->placeholder('/iphone/compare'),
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

                TextColumn::make('lc_header_text')
                    ->label('Header Text')
                    ->placeholder('—'),

                TextColumn::make('lc_header_btn_label')
                    ->label('Button')
                    ->placeholder('—'),
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
