<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ImportProductsPage;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ManageProductAddons;
use App\Filament\Resources\Products\Pages\ManageProductGalleries;
use App\Filament\Resources\Products\Pages\ManageProductRelations;
use App\Filament\Resources\Products\Pages\ManageProductVariants;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Models\Product;
use BackedEnum;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|UnitEnum|null $navigationGroup = 'คลังสินค้า';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Cube;

    protected static ?string $navigationLabel = 'สินค้า';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'pd_name';

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            EditProduct::class,
            ManageProductVariants::class,
            ManageProductGalleries::class,
            ManageProductRelations::class,
            ManageProductAddons::class,
        ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'    => ListProducts::route('/'),
            'create'   => CreateProduct::route('/create'),
            'import'   => ImportProductsPage::route('/import'),
            'edit'     => EditProduct::route('/{record}/edit'),
            'variants'   => ManageProductVariants::route('/{record}/variants'),
            'galleries'  => ManageProductGalleries::route('/{record}/galleries'),
            'relations'  => ManageProductRelations::route('/{record}/relations'),
            'addons'     => ManageProductAddons::route('/{record}/addons'),
        ];
    }
}
