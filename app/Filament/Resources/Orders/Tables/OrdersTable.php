<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ord_id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('store.st_name')
                    ->label('สาขา')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('ord_customer_name')
                    ->label('ชื่อลูกค้า')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('ord_total_amount')
                    ->label('ยอดรวม')
                    ->money('THB')
                    ->sortable(),

                TextColumn::make('ord_status')
                    ->label('สถานะ')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending'    => 'warning',
                        'processing' => 'info',
                        'completed'  => 'success',
                        'cancelled'  => 'danger',
                        default      => 'gray',
                    }),

                TextColumn::make('ord_date')
                    ->label('วันที่สั่งซื้อ')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('สร้างเมื่อ')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('ord_status')
                    ->label('สถานะ')
                    ->options([
                        'pending'    => 'รอดำเนินการ',
                        'processing' => 'กำลังดำเนินการ',
                        'completed'  => 'สำเร็จ',
                        'cancelled'  => 'ยกเลิก',
                    ]),

                SelectFilter::make('st_id')
                    ->label('สาขา')
                    ->relationship('store', 'st_name'),
            ])
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
