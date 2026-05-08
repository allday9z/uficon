<?php

namespace App\Filament\Resources\Stores\Schemas;

use App\Filament\Forms\Components\LeafletPicker;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class StoreForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('ข้อมูลสาขา')
                    ->schema([
                        Select::make('brand_id')
                            ->label('แบรนด์')
                            ->relationship('brand', 'brand_name')
                            ->createOptionForm([
                                TextInput::make('brand_name')
                                    ->label('ชื่อแบรนด์')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('brand_code')
                                    ->label('Brand Code')
                                    ->required()
                                    ->maxLength(50),
                                FileUpload::make('brand_icon')
                                    ->label('Brand Icon')
                                    ->image()
                                    ->maxSize(1024)
                                    ->directory('images/brands'),
                            ])
                            ->editOptionForm([
                                TextInput::make('brand_name')
                                    ->label('ชื่อแบรนด์')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('brand_code')
                                    ->label('Brand Code')
                                    ->required()
                                    ->maxLength(50),
                                FileUpload::make('brand_icon')
                                    ->label('Brand Icon')
                                    ->image()
                                    ->maxSize(1024)
                                    ->directory('images/brands'),
                            ])
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('st_name')
                            ->label('ชื่อสาขา')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('st_code')
                            ->label('รหัสสาขา (Store Code)')
                            ->maxLength(50)
                            ->helperText('เช่น CW01, T2101, SIAM01'),

                        Toggle::make('st_is_active')
                            ->label('เปิดใช้งาน')
                            ->default(true)
                            ->required(),

                        TagsInput::make('st_phone')
                            ->label('เบอร์โทรศัพท์')
                            ->placeholder('เพิ่มเบอร์โทร')
                            ->columnSpanFull(),

                        Repeater::make('st_contact_links')
                            ->label('Social Media / Contact Links')
                            ->schema([
                                Select::make('platform')
                                    ->options([
                                        'facebook'  => 'Facebook',
                                        'line'      => 'Line',
                                        'instagram' => 'Instagram',
                                        'website'   => 'Website',
                                        'other'     => 'Other',
                                    ])
                                    ->required(),
                                TextInput::make('url')
                                    ->url()
                                    ->required()
                                    ->label('Link URL'),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('ที่ตั้ง')
                    ->schema([
                        TextInput::make('google_map_url')
                            ->label('Google Maps Link')
                            ->placeholder('วาง Google Maps link เพื่อ auto-fill Lat/Long')
                            ->columnSpanFull()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Set $set) {
                                if (empty($state)) return;
                                if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $state, $m)) {
                                    $set('latitude', $m[1]);
                                    $set('longitude', $m[2]);
                                    $set('location', ['lat' => $m[1], 'lng' => $m[2]]);
                                } elseif (preg_match('/q=(-?\d+\.\d+),(-?\d+\.\d+)/', $state, $m)) {
                                    $set('latitude', $m[1]);
                                    $set('longitude', $m[2]);
                                    $set('location', ['lat' => $m[1], 'lng' => $m[2]]);
                                }
                            })
                            ->suffixAction(
                                Action::make('extract')
                                    ->icon('heroicon-m-arrow-path')
                                    ->label('Extract')
                                    ->action(function ($state, Set $set) {
                                        if (empty($state)) return;
                                        if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $state, $m)) {
                                            $set('latitude', $m[1]);
                                            $set('longitude', $m[2]);
                                            $set('location', ['lat' => $m[1], 'lng' => $m[2]]);
                                        } elseif (preg_match('/q=(-?\d+\.\d+),(-?\d+\.\d+)/', $state, $m)) {
                                            $set('latitude', $m[1]);
                                            $set('longitude', $m[2]);
                                            $set('location', ['lat' => $m[1], 'lng' => $m[2]]);
                                        }
                                    })
                            ),

                        Textarea::make('st_full_address')
                            ->label('ที่อยู่เต็ม')
                            ->rows(3)
                            ->columnSpanFull(),

                        Textarea::make('st_address')
                            ->label('ที่อยู่ (ย่อ)')
                            ->rows(2)
                            ->columnSpanFull(),

                        Grid::make(2)->schema([
                            TextInput::make('latitude')
                                ->numeric()
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                    $set('location', ['lat' => (float) $state, 'lng' => (float) $get('longitude')]);
                                }),
                            TextInput::make('longitude')
                                ->numeric()
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                    $set('location', ['lat' => (float) $get('latitude'), 'lng' => (float) $state]);
                                }),
                        ]),

                        LeafletPicker::make('location')
                            ->label('แผนที่')
                            ->columnSpanFull()
                            ->defaultLocation(13.7563, 100.5018)
                            ->defaultZoom(13)
                            ->height('400px')
                            ->latField('latitude')
                            ->lngField('longitude'),
                    ]),

                Section::make('รูปภาพ')
                    ->schema([
                        FileUpload::make('images')
                            ->label('รูปภาพสาขา')
                            ->multiple()
                            ->image()
                            ->columnSpanFull()
                            ->maxFiles(5)
                            ->disk('public')
                            ->directory(fn (Get $get): string => 'images/stores/' . ($get('st_code') ?: 'store')),
                    ]),
            ]);
    }
}
