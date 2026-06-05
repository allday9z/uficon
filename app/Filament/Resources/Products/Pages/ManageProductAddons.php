<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;

class ManageProductAddons extends ManageRelatedRecords
{
    protected static string $resource = ProductResource::class;

    protected static string $relationship = 'addonMaps';

    protected static ?string $navigationLabel = 'Add-ons';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('addon_pd_id')
                ->label('Add-on product (เช่น AppleCare+)')
                ->options(fn () => Product::orderBy('pd_name')
                    ->pluck('pd_name', 'pd_id')
                    ->except([$this->getOwnerRecord()->pd_id]))
                ->searchable()
                ->preload()
                ->required()
                ->columnSpanFull(),

            Toggle::make('is_required')
                ->label('Required — บังคับซื้อพร้อมกัน')
                ->default(false)
                ->helperText('ถ้าเปิด: ลูกค้าต้องเลือก add-on นี้เสมอ'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('pam_id')
            ->columns([
                TextColumn::make('addonProduct.pd_name')
                    ->label('Add-on product')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('addonProduct.price')
                    ->label('Price')
                    ->money('THB')
                    ->sortable(),

                IconColumn::make('is_required')
                    ->label('Required')
                    ->boolean()
                    ->trueIcon(Heroicon::CheckCircle)
                    ->falseIcon(Heroicon::XCircle),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
