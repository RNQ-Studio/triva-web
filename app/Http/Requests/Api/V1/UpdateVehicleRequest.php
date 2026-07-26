<?php

namespace App\Http\Requests\Api\V1;

class UpdateVehicleRequest extends StoreVehicleRequest
{
    public function authorize(): bool
    {
        $vehicle = $this->route('vehicle');

        return $vehicle !== null
            && ($this->user()?->can('update', $vehicle) ?? false);
    }
}
