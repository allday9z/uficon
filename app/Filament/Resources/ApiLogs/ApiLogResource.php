<?php

namespace App\Filament\Resources\ApiLogs;

use App\Filament\Resources\ApiLogs\Pages\ManageApiLogs;
use App\Models\ApiLog;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\KeyValue;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ApiLogResource extends Resource
{
    protected static ?string $model = ApiLog::class;

    protected static string | UnitEnum | null $navigationGroup = 'API Management';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowsRightLeft;

    protected static ?string $modelLabel = 'Logs Activity';

    protected static ?string $recordTitleAttribute = 'id';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('api_token_id')
                    ->label('Token ID')
                    ->disabled(),
                TextInput::make('method')
                    ->disabled(),
                TextInput::make('url')
                    ->label('URL')
                    ->disabled()
                    ->columnSpanFull(),
                TextInput::make('status_code')
                    ->disabled(),
                TextInput::make('ip_address')
                    ->label('IP Address')
                    ->disabled(),
                TextInput::make('duration_ms')
                    ->label('Duration (ms)')
                    ->disabled(),
                CodeEditor::make('payload')
                    ->label('Request Payload')
                    ->language(Language::Json)
                    ->columnSpanFull()
                    ->formatStateUsing(fn ($state): string => static::normalizeLogData($state))
                    ->disabled(),
                CodeEditor::make('response')
                    ->label('Response Body')
                    ->language(Language::Json)
                    ->columnSpanFull()
                    ->formatStateUsing(fn ($state): string => static::normalizeLogData($state))
                    ->disabled(),
            ]);
    }

    protected static function normalizeLogData(mixed $state): string
    {
        if (is_null($state)) {
            return '';
        }

        if (is_array($state) || is_object($state)) {
            return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        if (is_string($state)) {
            $decoded = json_decode($state, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $state;
            }
        }

        return (string) $state;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('apiToken.name')
                    ->label('Token Name')
                    ->sortable(),
                TextColumn::make('method')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'GET' => 'success',
                        'POST' => 'warning',
                        'PUT' => 'info',
                        'DELETE' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('url')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('status_code')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state >= 200 && $state < 300 => 'success',
                        $state >= 400 && $state < 500 => 'warning',
                        $state >= 500 => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('ip_address')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('duration_ms')
                    ->sortable()
                    ->label('Duration (ms)'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageApiLogs::route('/'),
        ];
    }
}
