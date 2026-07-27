<?php

namespace App\Filament\Resources\CreditSimulations\Pages;

use App\Filament\Resources\CreditSimulations\CreditSimulationResource;
use App\Filament\Resources\CreditSimulations\Widgets\CreditSimulationStats;
use Filament\Resources\Pages\ListRecords;

class ListCreditSimulations extends ListRecords
{
    protected static string $resource = CreditSimulationResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            CreditSimulationStats::class,
        ];
    }
}
