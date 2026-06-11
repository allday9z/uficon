<?php
namespace App\Filament\Resources\BadgePresets\Pages;
use App\Filament\Resources\BadgePresets\BadgePresetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
class ManageBadgePresets extends ManageRecords
{
    protected static string $resource = BadgePresetResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}
