<?php

namespace App\Filament\Resources\OtoxpertServices\Pages;

use App\Filament\Resources\OtoxpertServices\OtoxpertServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOtoxpertServices extends ListRecords
{
    protected static string $resource = OtoxpertServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
