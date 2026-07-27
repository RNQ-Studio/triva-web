<?php

namespace App\Filament\Resources\ToyotaServiceLocations\Pages;

use App\Filament\Resources\ToyotaServiceLocations\ToyotaServiceLocationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListToyotaServiceLocations extends ListRecords
{
    protected static string $resource = ToyotaServiceLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
