<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Store;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('st_id')
                    ->label('สาขา')
                    ->options(fn () => Store::where('st_is_active', true)
                        ->orderBy('st_name')
                        ->pluck('st_name', 'st_id'))
                    ->searchable()
                    ->required(),

                TextInput::make('ord_customer_name')
                    ->label('ชื่อลูกค้า')
                    ->required()
                    ->maxLength(255),

                TextInput::make('ord_total_amount')
                    ->label('ยอดรวม')
                    ->required()
                    ->numeric()
                    ->prefix('฿'),

                Select::make('ord_status')
                    ->label('สถานะ')
                    ->options([
                        'pending'    => 'รอดำเนินการ',
                        'processing' => 'กำลังดำเนินการ',
                        'completed'  => 'สำเร็จ',
                        'cancelled'  => 'ยกเลิก',
                    ])
                    ->default('pending')
                    ->required(),

                DatePicker::make('ord_date')
                    ->label('วันที่สั่งซื้อ')
                    ->required()
                    ->default(now()),
            ]);
    }
}
