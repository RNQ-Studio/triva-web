<?php

namespace App\Http\Resources\Api\V1;

use App\Models\VehicleBenefitCheck;
use App\Support\Enums\VehicleBenefitStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VehicleBenefitCheck */
class VehicleBenefitCheckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $effectiveStatus = $this->status;
        if (
            $effectiveStatus === VehicleBenefitStatus::Active
            && $this->valid_until !== null
            && $this->valid_until->isPast()
        ) {
            $effectiveStatus = VehicleBenefitStatus::Inactive;
        }

        return [
            'id' => $this->id,
            'type' => $this->benefit_type->value,
            'label' => $this->benefit_type->label(),
            'status' => $effectiveStatus->value,
            'status_label' => $effectiveStatus->label(),
            'valid_until' => $this->valid_until?->toIso8601String(),
            'verification_source' => $this->verification_source?->value,
        ];
    }
}
