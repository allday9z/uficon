<?php

namespace App\Filament\Resources\ApiTokens\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ApiTokenForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Token Name')
                    ->required()
                    ->maxLength(60)
                    ->default(fn () => 'UFICON-' . now()->format('YmdHis')),
                TextInput::make('token')
                    ->label('Token Key')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->default(fn () => "UF".bin2hex(random_bytes(10))."I".bin2hex(random_bytes(10)))
                    ->disabled()
                    ->dehydrated(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->required()
                    ->default(true),
            ]);
    }
}
