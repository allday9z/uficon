<?php
namespace App\Filament\Resources\BadgePresets;

use App\Filament\Resources\BadgePresets\Pages\ManageBadgePresets;
use App\Models\BadgePreset;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class BadgePresetResource extends Resource
{
    protected static ?string $model = BadgePreset::class;
    protected static string|UnitEnum|null $navigationGroup = 'คลังสินค้า';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;
    protected static ?string $navigationLabel = 'Badge Presets';
    protected static ?string $recordTitleAttribute = 'bp_text';
    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(2)->schema([
                TextInput::make('bp_text')
                    ->label('Badge Text')
                    ->required()
                    ->unique(ignorable: fn ($record) => $record)
                    ->maxLength(100)
                    ->placeholder('NEW, SALE, ใหม่…'),

                ColorPicker::make('bp_hex_color')
                    ->label('Color (hex)')
                    ->required(),

                Textarea::make('bp_purpose')
                    ->label('Purpose / Guidelines')
                    ->maxLength(500)
                    ->columnSpanFull()
                    ->rows(2),

                TextInput::make('bp_sort_order')
                    ->label('Sort order')
                    ->numeric()
                    ->default(99),

                Toggle::make('bp_is_active')
                    ->label('Active')
                    ->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bp_text')
                    ->label('Badge')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => $state)
                    ->html()
                    ->getStateUsing(fn ($record) =>
                        '<span style="background:' . e($record->bp_hex_color) . ';color:#fff;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:600;">'
                        . e($record->bp_text) . '</span>'
                    ),

                TextColumn::make('bp_hex_color')
                    ->label('Color')
                    ->formatStateUsing(fn ($state) =>
                        '<span style="background:' . e($state) . ';color:#fff;padding:2px 6px;border-radius:4px;font-size:11px;">' . e($state) . '</span>'
                    )
                    ->html(),

                TextColumn::make('bp_purpose')->label('Purpose')->limit(60)->color('gray'),

                IconColumn::make('bp_is_active')->label('Active')->boolean(),

                TextColumn::make('bp_sort_order')->label('#')->sortable(),
            ])
            ->defaultSort('bp_sort_order')
            ->reorderable('bp_sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->groupedBulkActions([\Filament\Actions\BulkActionGroup::make([\Filament\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBadgePresets::route('/')];
    }
}
