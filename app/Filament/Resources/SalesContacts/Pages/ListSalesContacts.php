<?php

namespace App\Filament\Resources\SalesContacts\Pages;

use App\Filament\Resources\SalesContacts\SalesContactResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSalesContacts extends ListRecords
{
    protected static string $resource = SalesContactResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
