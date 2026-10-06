<?php

namespace App\Filament\Resources\PartnerLogos\Pages;

use App\Filament\Resources\PartnerLogos\PartnerLogoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPartnerLogo extends EditRecord
{
    protected static string $resource = PartnerLogoResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
