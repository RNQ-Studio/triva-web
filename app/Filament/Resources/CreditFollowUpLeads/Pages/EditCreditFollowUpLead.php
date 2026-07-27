<?php

namespace App\Filament\Resources\CreditFollowUpLeads\Pages;

use App\Filament\Resources\CreditFollowUpLeads\CreditFollowUpLeadResource;
use App\Models\CreditFollowUpLead;
use App\Support\Enums\CreditLeadStatus;
use Filament\Resources\Pages\EditRecord;

class EditCreditFollowUpLead extends EditRecord
{
    protected static string $resource = CreditFollowUpLeadResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var CreditFollowUpLead $record */
        $record = $this->getRecord();
        if ($data['status'] === CreditLeadStatus::Contacted->value
            && $record->contacted_at === null) {
            $data['contacted_at'] = now();
        }
        if ($data['status'] === CreditLeadStatus::Converted->value
            && $record->converted_at === null) {
            $data['converted_at'] = now();
        }

        return $data;
    }
}
