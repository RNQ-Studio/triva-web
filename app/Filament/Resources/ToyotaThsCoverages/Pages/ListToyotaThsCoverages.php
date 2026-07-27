<?php

namespace App\Filament\Resources\ToyotaThsCoverages\Pages;

use App\Filament\Resources\ToyotaThsCoverages\ToyotaThsCoverageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListToyotaThsCoverages extends ListRecords
{
    protected static string $resource = ToyotaThsCoverageResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
