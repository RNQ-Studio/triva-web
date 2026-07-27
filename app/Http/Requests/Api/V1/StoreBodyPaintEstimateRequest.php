<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Appraisal;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreBodyPaintEstimateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    public function authorize(): bool
    {
        $userId = $this->user()?->getKey();
        if ($userId === null) {
            return false;
        }

        $vehicleId = $this->input('vehicle_id');
        if (is_string($vehicleId) && Str::isUuid($vehicleId)) {
            $vehicle = Vehicle::query()->find($vehicleId);
            if ($vehicle !== null && $vehicle->user_id !== $userId) {
                return false;
            }
        }

        $appraisalId = $this->input('appraisal_id');
        if (is_string($appraisalId) && Str::isUuid($appraisalId)) {
            $appraisal = Appraisal::query()->find($appraisalId);
            if ($appraisal !== null && $appraisal->user_id !== $userId) {
                return false;
            }
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'appraisal_id' => ['nullable', 'uuid', 'exists:appraisals,id'],
            'service_location_id' => [
                'nullable',
                'uuid',
                Rule::exists('toyota_service_locations', 'id')
                    ->where('is_active', true)
                    ->where('supports_workshop', true),
            ],
            'customer_notes' => ['nullable', 'string', 'max:3000'],
            'campaign_source' => ['nullable', 'string', 'max:100'],
            'campaign_metadata' => [
                'nullable',
                'array:utm_source,utm_medium,utm_campaign,utm_content,utm_term',
            ],
            'campaign_metadata.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
