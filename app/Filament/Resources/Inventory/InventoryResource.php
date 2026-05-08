<?php

namespace App\Filament\Resources\Inventory;

use App\Filament\Resources\Inventory\Pages\EditInventory;
use App\Filament\Resources\Inventory\Pages\ListInventory;
use App\Models\Inventory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class InventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;

    protected static ?string $navigationLabel = 'คลังสินค้า (Stock)';

    protected static string|UnitEnum|null $navigationGroup = 'คลังสินค้า';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArchiveBox;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Inventory';

    protected static ?string $recordTitleAttribute = 'inv_id';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Forms\Components\TextInput::make('qty_available')
                ->label('On Hand')
                ->numeric()
                ->required()
                ->minValue(0),

            \Filament\Forms\Components\TextInput::make('qty_reserved')
                ->label('Committed')
                ->numeric()
                ->required()
                ->minValue(0),

            \Filament\Forms\Components\TextInput::make('qty_damaged')
                ->label('Unavailable')
                ->numeric()
                ->required()
                ->minValue(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return Pages\ListInventory::configureTable($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventory::route('/'),
            'edit'  => EditInventory::route('/{record}/edit'),
        ];
    }
}
