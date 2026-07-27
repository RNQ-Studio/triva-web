<?php

namespace App\Filament\Resources\CreditPrograms\Pages;

use App\Filament\Resources\CreditPrograms\CreditProgramResource;
use App\Models\CreditProgram;
use App\Support\Enums\CreditProgramStatus;
use Filament\Resources\Pages\EditRecord;

class EditCreditProgram extends EditRecord
{
    protected static string $resource = CreditProgramResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var CreditProgram $record */
        $record = $this->getRecord();
        if ($data['status'] === CreditProgramStatus::Approved->value
            && $record->approved_at === null) {
            $data['approved_by'] = auth()->id();
            $data['approved_at'] = now();
        }

        return $data;
    }
}
