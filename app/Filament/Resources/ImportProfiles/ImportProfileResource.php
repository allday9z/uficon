<?php

namespace App\Filament\Resources\ImportProfiles;

use App\Filament\Resources\ImportProfiles\Pages\CreateImportProfile;
use App\Filament\Resources\ImportProfiles\Pages\EditImportProfile;
use App\Filament\Resources\ImportProfiles\Pages\ListImportProfiles;
use App\Models\ImportProfile;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ImportProfileResource extends Resource
{
    protected static ?string $model = ImportProfile::class;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowUpTray;

    protected static ?string $navigationLabel = 'Import Profiles';

    protected static ?int $navigationSort = 50;

    protected static ?string $recordTitleAttribute = 'ip_name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)->schema([
                TextInput::make('ip_name')
                    ->label('Profile Name')
                    ->required()
                    ->maxLength(100)
                    ->columnSpan(2),

                TextInput::make('ip_slug')
                    ->label('Slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50)
                    ->helperText('Used in --profile= flag')
                    ->columnSpan(1),

                TextInput::make('ip_sheet_name')
                    ->label('Sheet Name (contains)')
                    ->nullable()
                    ->maxLength(100)
                    ->helperText('Matches sheet name substring, e.g. "Product"')
                    ->columnSpan(1),

                TextInput::make('ip_header_row')
                    ->label('Header Row')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->columnSpan(1),

                Textarea::make('ip_description')
                    ->label('Description')
                    ->rows(2)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ip_name')
                    ->label('Profile')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('ip_slug')
                    ->label('Slug')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('ip_sheet_name')
                    ->label('Sheet')
                    ->placeholder('—'),

                TextColumn::make('columnMaps_count')
                    ->label('Column Maps')
                    ->counts('columnMaps')
                    ->badge()
                    ->color('info'),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListImportProfiles::route('/'),
            'create' => CreateImportProfile::route('/create'),
            'edit'   => EditImportProfile::route('/{record}/edit'),
        ];
    }

    /** Fields available in the column map form (used in Repeater) */
    public static function columnMapSchema(): array
    {
        return [
            Grid::make(12)->schema([
                TextInput::make('icm_source_header')
                    ->label('Source Header')
                    ->nullable()
                    ->columnSpan(3),

                TextInput::make('icm_source_index')
                    ->label('Col Index')
                    ->numeric()
                    ->nullable()
                    ->minValue(0)
                    ->columnSpan(1),

                Select::make('icm_target_model')
                    ->label('Model')
                    ->options([
                        'Product'           => 'Product',
                        'ProductVariant'    => 'ProductVariant',
                        'ProductMedia'      => 'ProductMedia',
                        'ProductInbox'      => 'ProductInbox',
                        'ProductCollection' => 'ProductCollection',
                        'Brand'             => 'Brand',
                    ])
                    ->required()
                    ->columnSpan(2),

                TextInput::make('icm_target_field')
                    ->label('Target Field')
                    ->required()
                    ->columnSpan(2),

                Select::make('icm_update_mode')
                    ->label('Update Mode')
                    ->options([
                        'always'       => 'Always',
                        'create_only'  => 'Create Only',
                        'skip'         => 'Skip',
                    ])
                    ->default('always')
                    ->required()
                    ->columnSpan(2),

                Select::make('icm_cast')
                    ->label('Cast')
                    ->options([
                        'string' => 'string',
                        'int'    => 'int',
                        'float'  => 'float',
                        'bool'   => 'bool',
                        'slug'   => 'slug',
                    ])
                    ->nullable()
                    ->columnSpan(1),

                TextInput::make('icm_default_value')
                    ->label('Default')
                    ->nullable()
                    ->columnSpan(2),

                Toggle::make('icm_required')
                    ->label('Required')
                    ->default(false)
                    ->columnSpan(1),

                TextInput::make('icm_position')
                    ->label('Order')
                    ->numeric()
                    ->default(0)
                    ->columnSpan(1),
            ]),
        ];
    }
}
