<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ToyotaServiceLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ToyotaServiceLocation */
class ToyotaServiceLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'phone' => $this->phone,
            'latitude' => $this->latitude === null ? null : (float) $this->latitude,
            'longitude' => $this->longitude === null ? null : (float) $this->longitude,
            'directions_url' => $this->directions_url,
            'timezone' => $this->timezone,
            'supports_workshop' => $this->supports_workshop,
            'supports_ths' => $this->supports_ths,
            'operating_hours' => $this->operating_hours,
            'confirmation_sla_minutes' => $this->confirmation_sla_minutes,
            'cancellation_cutoff_hours' => $this->cancellation_cutoff_hours,
            'effective_from' => $this->effective_from->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
        ];
    }
}
