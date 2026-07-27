<?php

namespace App\Filament\Resources\OtoxpertWorkshops\Pages;

use App\Filament\Resources\OtoxpertWorkshops\OtoxpertWorkshopResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOtoxpertWorkshops extends ListRecords
{
    protected static string $resource = OtoxpertWorkshopResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
