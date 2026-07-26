<?php

namespace App\Http\Resources\Api\V1;

use App\Models\VehicleModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VehicleModel */
class VehicleModelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'make_id' => $this->vehicle_make_id,
            'slug' => $this->slug,
            'name' => $this->name,
        ];
    }
}
