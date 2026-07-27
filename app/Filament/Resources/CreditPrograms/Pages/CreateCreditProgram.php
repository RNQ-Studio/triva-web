<?php

namespace App\Filament\Resources\CreditPrograms\Pages;

use App\Filament\Resources\CreditPrograms\CreditProgramResource;
use App\Support\Enums\CreditProgramStatus;
use Filament\Resources\Pages\CreateRecord;

class CreateCreditProgram extends CreateRecord
{
    protected static string $resource = CreditProgramResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($data['status'] === CreditProgramStatus::Approved->value) {
            $data['approved_by'] = auth()->id();
            $data['approved_at'] = now();
        }

        return $data;
    }
}
