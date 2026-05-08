<?php

namespace App\Filament\Resources\Inventory\Pages;

use App\Filament\Resources\Inventory\InventoryResource;
use App\Models\Store;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ListInventory extends ListRecords
{
    protected static string $resource = InventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public static function configureTable(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('variant.product.featuredImage')
                    ->label('')
                    ->width(44)
                    ->height(44)
                    ->extraImgAttributes(['class' => 'rounded object-cover'])
                    ->defaultImageUrl('https://placehold.co/44x44/f3f4f6/9ca3af?text=—'),

                TextColumn::make('variant.product.pd_name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn ($record): string =>
                        collect([$record->variant?->pv_option1, $record->variant?->pv_option2, $record->variant?->pv_option3])
                            ->filter()
                            ->implode(' / ')
                    ),

                TextColumn::make('variant.pv_sku')
                    ->label('SKU')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono')
                    ->color('gray'),

                TextColumn::make('store.st_name')
                    ->label('สาขา')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('qty_damaged')
                    ->label('Unavailable')
                    ->alignCenter()
                    ->color(fn ($state): string => $state > 0 ? 'danger' : 'gray'),

                TextColumn::make('qty_reserved')
                    ->label('Committed')
                    ->alignCenter()
                    ->color(fn ($state): string => $state > 0 ? 'warning' : 'gray'),

                TextColumn::make('qty_usable')
                    ->label('Available')
                    ->alignCenter()
                    ->getStateUsing(fn ($record): int => max(0, $record->qty_available - $record->qty_reserved))
                    ->color(fn ($record): string => ($record->qty_available - $record->qty_reserved) <= 0 ? 'danger' : 'success'),

                TextInputColumn::make('qty_available')
                    ->label('On Hand')
                    ->type('number')
                    ->rules(['integer', 'min:0'])
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('st_id')
                    ->label('สาขา')
                    ->options(fn (): array => Store::pluck('st_name', 'st_id')->toArray())
                    ->placeholder('ทุกสาขา'),

                SelectFilter::make('low_stock')
                    ->label('Stock ต่ำ (< 5)')
                    ->options(['1' => 'แสดงเฉพาะ Stock ต่ำ'])
                    ->query(fn ($query, $state) =>
                        $state['value'] === '1' ? $query->where('qty_available', '<', 5) : $query
                    ),
            ])
            ->recordActions([
                EditAction::make()->label('แก้ไข'),
            ])
            ->defaultSort('updated_at', 'desc')
            ->paginated([25, 50, 100]);
    }
}
