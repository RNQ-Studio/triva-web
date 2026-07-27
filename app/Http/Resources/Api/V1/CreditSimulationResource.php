<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CreditSimulation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CreditSimulation */
class CreditSimulationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'comparison_group_id' => $this->comparison_group_id,
            'program' => $this->program_snapshot,
            'inputs' => $this->input_snapshot,
            'calculation' => $this->calculation_snapshot,
            'formula_version' => $this->formula_version,
            'valid_until' => $this->valid_until?->toDateString(),
            'is_program_expired' => $this->valid_until
                ?->copy()
                ->endOfDay()
                ->isPast() ?? false,
            'is_estimate' => true,
            'disclaimer' => 'Hasil simulasi bersifat estimasi dan bukan persetujuan kredit.',
            'follow_up' => $this->whenLoaded(
                'followUpLead',
                fn (): ?array => $this->followUpLead === null ? null : [
                    'id' => $this->followUpLead->id,
                    'reference_no' => $this->followUpLead->reference_no,
                    'status' => $this->followUpLead->status->value,
                    'status_label' => $this->followUpLead->status->label(),
                    'contact_channel' => $this->followUpLead->contact_channel,
                    'requested_at' => $this->followUpLead->created_at
                        ?->toIso8601String(),
                ],
            ),
            'campaign_source' => $this->campaign_source,
            'saved_at' => $this->saved_at->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
