<?php

namespace App\Http\Resources\Api\V1;

use App\Models\VehicleMake;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VehicleMake */
class VehicleMakeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'logo_url' => filled($this->logo_path)
                ? url($this->logo_path)
                : null,
        ];
    }
}
