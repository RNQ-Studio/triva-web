<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ToyotaThsCoverage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ToyotaThsCoverage */
class ToyotaThsCoverageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_location_id' => $this->service_location_id,
            'city' => $this->city,
            'verification_source' => $this->verification_source,
            'bounds' => $this->latitude_min === null ? null : [
                'latitude_min' => (float) $this->latitude_min,
                'latitude_max' => (float) $this->latitude_max,
                'longitude_min' => (float) $this->longitude_min,
                'longitude_max' => (float) $this->longitude_max,
            ],
            'effective_from' => $this->effective_from->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
        ];
    }
}
