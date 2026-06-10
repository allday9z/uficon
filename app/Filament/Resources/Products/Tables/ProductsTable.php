<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('featuredImage')
                    ->label('')
                    ->width(48)
                    ->height(48)
                    ->extraImgAttributes(['class' => 'rounded object-cover'])
                    ->defaultImageUrl('https://placehold.co/48x48/f3f4f6/9ca3af?text=—'),

                TextColumn::make('pd_name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn ($record): string => $record->pd_handle ?? ''),

                TextColumn::make('pd_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active'   => 'success',
                        'draft'    => 'warning',
                        'inactive' => 'danger',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('inventory_summary')
                    ->label('Inventory')
                    ->getStateUsing(function ($record): string {
                        $total = (int) $record->variants()
                            ->join('inventory', 'product_variant.pv_id', '=', 'inventory.pv_id')
                            ->whereNull('inventory.deleted_at')
                            ->sum('inventory.qty_available');
                        return $total . ' in stock';
                    })
                    ->description(fn ($record): string => $record->variants()->count() . ' variants')
                    ->color(function ($record): string {
                        $total = (int) $record->variants()
                            ->join('inventory', 'product_variant.pv_id', '=', 'inventory.pv_id')
                            ->whereNull('inventory.deleted_at')
                            ->sum('inventory.qty_available');
                        return $total === 0 ? 'danger' : 'success';
                    }),

                TextColumn::make('pd_lob')
                    ->label('LOB')
                    ->badge()
                    ->sortable()
                    ->color(fn (?string $state): string => match ($state) {
                        'Mac'         => 'info',
                        'iPhone'      => 'success',
                        'iPad'        => 'warning',
                        'Apple Watch' => 'primary',
                        'AirPods'     => 'danger',
                        default       => 'gray',
                    }),

                TextColumn::make('pd_sub_lob')
                    ->label('Sub LOB')
                    ->sortable()
                    ->searchable()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('brand.brand_name')
                    ->label('Vendor')
                    ->sortable()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('สร้างเมื่อ')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('pd_status')
                    ->label('Status')
                    ->options([
                        'active'   => 'Active',
                        'draft'    => 'Draft',
                        'inactive' => 'Inactive',
                    ]),

                SelectFilter::make('pd_lob')
                    ->label('LOB')
                    ->options(fn () => Product::whereNotNull('pd_lob')
                        ->distinct()->orderBy('pd_lob')->pluck('pd_lob', 'pd_lob')->toArray()
                    )
                    ->searchable(),

                SelectFilter::make('pd_sub_lob')
                    ->label('Sub LOB')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) =>
                        Product::whereNotNull('pd_sub_lob')
                            ->where('pd_sub_lob', 'ilike', "%{$search}%")
                            ->distinct()
                            ->pluck('pd_sub_lob', 'pd_sub_lob')
                            ->toArray()
                    )
                    ->getOptionLabelUsing(fn (string $value): string => $value),

                SelectFilter::make('brand_id')
                    ->label('Vendor')
                    ->relationship('brand', 'brand_name'),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersTriggerAction(
                fn (Action $action) => $action->button()->label('ตัวกรอง')
            )
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
