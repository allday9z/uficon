<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductVariantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label('')
                    ->getStateUsing(fn ($record): ?string => $record->media()->value('pm_src'))
                    ->width(56)
                    ->height(56)
                    ->extraImgAttributes(['class' => 'rounded object-cover'])
                    ->defaultImageUrl('https://placehold.co/56x56/f3f4f6/9ca3af?text=—'),

                TextColumn::make('option_values')
                    ->label('Variant')
                    ->getStateUsing(fn ($record): string =>
                        collect(range(1, 7))
                            ->map(fn ($i) => $record->{'pv_option' . $i})
                            ->filter()
                            ->implode(' / ')
                        ?: ($record->pv_title ?? '—')
                    )
                    ->weight('semibold')
                    ->description(fn ($record): string => $record->pv_handle
                        ? '/products/' . $record->pv_handle
                        : '— no slug —'
                    )
                    ->searchable(query: fn ($query, $search) =>
                        $query->where(function ($q) use ($search) {
                            foreach (range(1, 7) as $i) {
                                $q->orWhere('pv_option' . $i, 'like', "%{$search}%");
                            }
                            $q->orWhere('pv_title', 'like', "%{$search}%");
                        })
                    ),

                TextColumn::make('pv_mpn')
                    ->label('MPN')
                    ->copyable()
                    ->color('gray')
                    ->searchable(),

                TextColumn::make('price')
                    ->label('Price')
                    ->formatStateUsing(fn ($state): string => '฿' . number_format((float) $state, 0))
                    ->sortable(),

                TextColumn::make('media_count')
                    ->label('Images')
                    ->getStateUsing(fn ($record): string => $record->media()->count() . ' images')
                    ->color('gray'),

                IconColumn::make('pv_available')
                    ->label('Available')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('5xl'),

                DeleteAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make()
                    ->slideOver()
                    ->modalWidth('5xl'),
            ])
            ->defaultSort('pv_option1');
    }
}
