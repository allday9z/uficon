<?php

namespace App\Filament\Resources\Stores\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('st_id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('brand.brand_name')
                    ->label('แบรนด์')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('st_name')
                    ->label('ชื่อสาขา')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('st_code')
                    ->label('รหัสสาขา')
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('st_full_address')
                    ->label('ที่อยู่')
                    ->limit(50)
                    ->toggleable(),

                TextColumn::make('latitude')
                    ->label('Lat')
                    ->numeric(4)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('longitude')
                    ->label('Long')
                    ->numeric(4)
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('st_is_active')
                    ->label('สถานะ')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('st_id');
    }
}
