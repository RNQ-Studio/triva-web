<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ListOtoxpertWorkshopsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vehicleId = $this->input('vehicle_id');
        if (! is_string($vehicleId) || ! Str::isUuid($vehicleId)) {
            return $this->user() !== null;
        }

        $vehicle = Vehicle::query()->find($vehicleId);

        return $vehicle === null
            || $vehicle->user_id === $this->user()?->getKey();
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'service_id' => [
                'nullable',
                'uuid',
                'exists:otoxpert_services,id',
            ],
            'city' => ['nullable', 'string', 'max:100'],
        ];
    }
}
