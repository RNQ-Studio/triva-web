<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Vehicle */
class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'make' => $this->make,
            'model' => $this->model,
            'variant' => $this->variant,
            'year' => $this->year,
            'transmission' => $this->transmission,
            'fuel_type' => $this->fuel_type,
            'mileage' => $this->mileage,
            'color' => $this->color,
            'license_plate' => $this->license_plate,
            'city' => $this->city,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
