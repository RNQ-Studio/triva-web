<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OtoxpertWorkshop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OtoxpertWorkshop */
class OtoxpertWorkshopResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'partner_code' => $this->partner_code,
            'name' => $this->name,
            'address' => $this->address,
            'province' => $this->province,
            'city' => $this->city,
            'latitude' => $this->latitude === null
                ? null
                : (float) $this->latitude,
            'longitude' => $this->longitude === null
                ? null
                : (float) $this->longitude,
            'phone' => $this->phone,
            'timezone' => $this->timezone,
            'operating_hours' => $this->operating_hours,
            'supports_pickup_delivery' => $this->supports_pickup_delivery,
            'confirmation_sla_minutes' => $this->confirmation_sla_minutes,
            'cancellation_cutoff_hours' => $this->cancellation_cutoff_hours,
            'services' => OtoxpertServiceResource::collection(
                $this->whenLoaded('services')
            ),
            'verified_at' => $this->verified_at->toIso8601String(),
        ];
    }
}
