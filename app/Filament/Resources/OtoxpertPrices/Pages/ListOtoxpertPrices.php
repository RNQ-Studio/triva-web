<?php

namespace App\Filament\Resources\OtoxpertPrices\Pages;

use App\Filament\Resources\OtoxpertPrices\OtoxpertPriceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOtoxpertPrices extends ListRecords
{
    protected static string $resource = OtoxpertPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
