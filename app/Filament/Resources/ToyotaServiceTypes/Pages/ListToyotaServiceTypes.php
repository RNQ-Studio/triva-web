<?php

namespace App\Filament\Resources\ToyotaServiceTypes\Pages;

use App\Filament\Resources\ToyotaServiceTypes\ToyotaServiceTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListToyotaServiceTypes extends ListRecords
{
    protected static string $resource = ToyotaServiceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
