<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class AdminVehicleBenefitCheckResource extends VehicleBenefitCheckResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'recorded_status' => $this->status->value,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'verified_by' => $this->verifiedBy === null ? null : [
                'id' => $this->verifiedBy->getKey(),
                'name' => $this->verifiedBy->name,
            ],
            'notes' => $this->notes,
        ];
    }
}
