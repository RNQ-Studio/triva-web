<?php

namespace App\Filament\Resources\MarketDataSources\Pages;

use App\Filament\Resources\MarketDataSources\MarketDataSourceResource;
use Filament\Resources\Pages\ListRecords;

class ListMarketDataSources extends ListRecords
{
    protected static string $resource = MarketDataSourceResource::class;
}
