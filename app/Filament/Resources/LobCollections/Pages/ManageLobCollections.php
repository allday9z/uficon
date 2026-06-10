<?php

namespace App\Filament\Resources\LobCollections\Pages;

use App\Filament\Resources\LobCollections\LobCollectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLobCollections extends ManageRecords
{
    protected static string $resource = LobCollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
