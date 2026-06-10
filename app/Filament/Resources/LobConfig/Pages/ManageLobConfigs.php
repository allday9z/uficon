<?php

namespace App\Filament\Resources\LobConfig\Pages;

use App\Filament\Resources\LobConfig\LobConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLobConfigs extends ManageRecords
{
    protected static string $resource = LobConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
