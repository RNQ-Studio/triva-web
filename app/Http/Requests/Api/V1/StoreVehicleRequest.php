<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'make' => ['required', 'string', 'max:80'],
            'model' => ['required', 'string', 'max:100'],
            'variant' => ['required', 'string', 'max:120'],
            'year' => ['required', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'transmission' => ['required', Rule::in(['automatic', 'manual'])],
            'fuel_type' => ['required', Rule::in(['gasoline', 'diesel', 'hybrid', 'electric'])],
            'mileage' => ['required', 'integer', 'min:0', 'max:2000000'],
            'color' => ['required', 'string', 'max:60'],
            'license_plate' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\s-]+$/'],
            'city' => ['required', 'string', 'max:100'],
        ];
    }
}
