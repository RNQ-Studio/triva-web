<?php

namespace App\Filament\Resources\ToyotaServicePackages\Pages;

use App\Filament\Resources\ToyotaServicePackages\ToyotaServicePackageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditToyotaServicePackage extends EditRecord
{
    protected static string $resource = ToyotaServicePackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
