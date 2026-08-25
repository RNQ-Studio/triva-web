<?php

namespace App\Filament\Resources\ToyotaSscCampaigns\Pages;

use App\Filament\Resources\ToyotaSscCampaigns\ToyotaSscCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListToyotaSscCampaigns extends ListRecords
{
    protected static string $resource = ToyotaSscCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
