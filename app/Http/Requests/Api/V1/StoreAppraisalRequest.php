<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;

class StoreAppraisalRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    public function authorize(): bool
    {
        $vehicleId = $this->string('vehicle_id')->toString();

        return $this->user() !== null
            && Vehicle::query()
                ->whereKey($vehicleId)
                ->where('user_id', $this->user()->getKey())
                ->exists();
    }

    public function rules(): array
    {
        return [
            // Optional for backward compatibility with released API v1 clients.
            'idempotency_key' => ['nullable', 'uuid'],
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
        ];
    }
}
