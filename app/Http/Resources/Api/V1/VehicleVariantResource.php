<?php

namespace App\Http\Resources\Api\V1;

use App\Models\VehicleVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VehicleVariant */
class VehicleVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'model_id' => $this->vehicle_model_id,
            'name' => $this->name,
            'year_from' => $this->year_from,
            'year_to' => $this->year_to,
            'transmission' => $this->transmission,
            'fuel_type' => $this->fuel_type,
        ];
    }
}
