<?php

namespace App\Filament\Resources\InfoPopups\Pages;

use App\Filament\Resources\InfoPopups\InfoPopupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInfoPopup extends EditRecord
{
    protected static string $resource = InfoPopupResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
