<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\Schemas\ProductVariantForm;
use App\Filament\Resources\Products\Tables\ProductVariantsTable;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ManageProductVariants extends ManageRelatedRecords
{
    protected static string $resource = ProductResource::class;

    protected static string $relationship = 'variants';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSquaresPlus;

    protected static ?string $navigationLabel = 'Variants';

    protected static ?string $title = 'Variants';

    public function form(Schema $schema): Schema
    {
        return ProductVariantForm::configure($schema, $this->getOwnerRecord());
    }

    public function table(Table $table): Table
    {
        return ProductVariantsTable::configure($table);
    }
}
