<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class MaintenanceEstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'vehicle_model' => ['nullable', 'string', 'max:100'],
            'mileage' => ['nullable', 'integer', 'min:0', 'max:2000000'],
        ];
    }
}
