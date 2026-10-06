<?php

namespace App\Filament\Resources\InfoPopups\Pages;

use App\Filament\Resources\InfoPopups\InfoPopupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInfoPopups extends ListRecords
{
    protected static string $resource = InfoPopupResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
