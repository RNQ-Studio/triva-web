<?php

namespace App\Filament\Resources\ToyotaServicePackages\Pages;

use App\Filament\Resources\ToyotaServicePackages\ToyotaServicePackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListToyotaServicePackages extends ListRecords
{
    protected static string $resource = ToyotaServicePackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
