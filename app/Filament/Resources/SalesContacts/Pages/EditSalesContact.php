<?php

namespace App\Filament\Resources\SalesContacts\Pages;

use App\Filament\Resources\SalesContacts\SalesContactResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSalesContact extends EditRecord
{
    protected static string $resource = SalesContactResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
