<?php

namespace App\Filament\Resources\ToyotaServiceHolidays\Pages;

use App\Filament\Resources\ToyotaServiceHolidays\ToyotaServiceHolidayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListToyotaServiceHolidays extends ListRecords
{
    protected static string $resource = ToyotaServiceHolidayResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
