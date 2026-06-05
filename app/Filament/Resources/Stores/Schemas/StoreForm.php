<?php

namespace App\Filament\Resources\Stores\Schemas;

use App\Filament\Forms\Components\LeafletPicker;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

                        TextInput::make('st_hours')
                            ->label('เวลาทำการ')
                            ->placeholder('เช่น เปิดทุกวัน 10:00 - 21:00')
                            ->columnSpanFull(),

                        Repeater::make('st_services')
                            ->label('บริการสาขา')
                            ->schema([
                                TextInput::make('label')
                                    ->label('ชื่อบริการ')
                                    ->required()
                                    ->placeholder('เช่น Apple Premium Partner'),
                                TextInput::make('url')
                                    ->label('URL')
                                    ->placeholder('/')
                                    ->default('/'),
                            ])
                            ->columns(2)
                            ->addActionLabel('+ เพิ่มบริการ')
                            ->defaultItems(0)
                            ->reorderableWithDragAndDrop()
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
                    ->columnSpanFull()
                    ->headerActions([
                        Action::make('verify_all_images')
                            ->label('Verify All')
                            ->icon('heroicon-o-arrow-down-on-square-stack')
                            ->color('gray')
                            ->action(function (Get $get, Set $set) {
                                $items   = $get('images') ?? [];
                                $stored  = 0;
                                $appUrl  = rtrim(config('app.url'), '/');
                                foreach ($items as $key => $item) {
                                    $url = $item['url'] ?? '';
                                    if (empty($url) || ! filter_var($url, FILTER_VALIDATE_URL)) continue;
                                    if (str_starts_with($url, $appUrl)) continue;
                                    try {
                                        $response = Http::timeout(20)
                                            ->withHeaders(['User-Agent' => 'UFicon-MediaImporter/1.0'])
                                            ->get($url);
                                        if (! $response->successful()) continue;
                                        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg');
                                        $ext = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif']) ? $ext : 'jpg';
                                        $path = 'images/stores/' . Str::uuid() . '.' . $ext;
                                        Storage::disk('public')->put($path, $response->body());
                                        $items[$key]['url'] = Storage::disk('public')->url($path);
                                        $stored++;
                                    } catch (\Exception $e) {
                                        continue;
                                    }
                                }
                                $set('images', $items);
                                Notification::make()->success()->title("Stored {$stored} image(s)")->send();
                            }),
                    ])
                    ->schema([
                        Repeater::make('images')
                            ->label('')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('url')
                                        ->label('Image URL / path')
                                        ->placeholder('https://...')
                                        ->live(onBlur: true)
                                        ->columnSpan(2)
                                        ->suffixActions([
                                            Action::make('upload_store_img')
                                                ->label('Upload')
                                                ->icon('heroicon-o-arrow-up-tray')
                                                ->modalHeading('Upload รูปสาขา')
                                                ->modalWidth('lg')
                                                ->schema([
                                                    FileUpload::make('upload')
                                                        ->label('เลือกรูป')
                                                        ->disk('public')
                                                        ->directory(fn (Get $get): string => 'images/stores/' . ($get('st_code') ?: 'store'))
                                                        ->image()
                                                        ->maxSize(5120)
                                                        ->required(),
                                                ])
                                                ->action(function (array $data, callable $set) {
                                                    if (empty($data['upload'])) return;
                                                    $set('url', Storage::disk('public')->url($data['upload']));
                                                }),

                                            Action::make('verify_store_img')
                                                ->label('Verify & Store')
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
                                                        $ext  = strtolower(pathinfo(parse_url($state, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg');
                                                        $ext  = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif']) ? $ext : 'jpg';
                                                        $path = 'images/stores/' . Str::uuid() . '.' . $ext;
                                                        Storage::disk('public')->put($path, $response->body());
                                                        $set('url', Storage::disk('public')->url($path));
                                                        Notification::make()->success()->title('Stored: ' . basename($path))->send();
                                                    } catch (\Exception $e) {
                                                        Notification::make()->danger()->title($e->getMessage())->send();
                                                    }
                                                }),
                                        ]),

                                    Placeholder::make('img_preview')
                                        ->label('Preview')
                                        ->columnSpan(1)
                                        ->content(fn (Get $get): \Illuminate\Support\HtmlString =>
                                            filled($get('url'))
                                                ? new \Illuminate\Support\HtmlString('<img src="' . e($get('url')) . '" class="h-20 w-full object-cover rounded border border-gray-200" loading="lazy" />')
                                                : new \Illuminate\Support\HtmlString('<div class="h-20 rounded border border-dashed border-gray-300 flex items-center justify-center text-xs text-gray-400">no image</div>')
                                        ),
                                ]),
                            ])
                            ->addActionLabel('+ เพิ่มรูป')
                            ->defaultItems(0)
                            ->reorderableWithDragAndDrop()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
