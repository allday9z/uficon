<?php

namespace App\Filament\Resources\Reports\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Select::make('type')
                    ->options([
                        'sales' => 'Sales Report',
                        'inventory' => 'Inventory Report',
                        'customer' => 'Customer Report',
                    ])
                    ->required(),

                DateTimePicker::make('generated_at')
                    ->required(),

                KeyValue::make('data')
                    ->columnSpanFull(),
            ]);
    }
}
