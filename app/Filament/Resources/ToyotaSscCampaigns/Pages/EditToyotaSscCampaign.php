<?php

namespace App\Filament\Resources\ToyotaSscCampaigns\Pages;

use App\Filament\Resources\ToyotaSscCampaigns\ToyotaSscCampaignResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditToyotaSscCampaign extends EditRecord
{
    protected static string $resource = ToyotaSscCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
