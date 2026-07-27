<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Appraisal;
use App\Models\Vehicle;
use App\Support\Enums\ToyotaServiceContactChannel;
use App\Support\Enums\ToyotaServiceFulfillmentType;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreToyotaServiceBookingRequest extends FormRequest
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
        $vehicleId = $this->input('vehicle_id');

        if ($userId === null) {
            return false;
        }

        if (is_string($vehicleId) && Str::isUuid($vehicleId)) {
            $vehicle = Vehicle::query()->find($vehicleId);
            if ($vehicle !== null && $vehicle->user_id !== $userId) {
                return false;
            }
        }

        $appraisalId = $this->input('source_appraisal_id');
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
        $userId = $this->user()?->getKey();

        return [
            'idempotency_key' => ['required', 'uuid'],
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'service_location_id' => [
                'required',
                'uuid',
                Rule::exists('toyota_service_locations', 'id')->where('is_active', true),
            ],
            'service_type_id' => [
                'required',
                'uuid',
                Rule::exists('toyota_service_types', 'id')->where('is_active', true),
            ],
            'fulfillment_type' => ['required', Rule::enum(ToyotaServiceFulfillmentType::class)],
            'current_mileage' => ['required', 'integer', 'min:0', 'max:5000000'],
            'complaint' => ['required', 'string', 'min:5', 'max:3000'],
            'primary_slot' => ['required', 'array:date,time_window'],
            'primary_slot.date' => ['required', 'date_format:Y-m-d'],
            'primary_slot.time_window' => ['required', 'regex:/^\d{2}:\d{2}-\d{2}:\d{2}$/'],
            'alternative_slot' => ['required', 'array:date,time_window'],
            'alternative_slot.date' => ['required', 'date_format:Y-m-d'],
            'alternative_slot.time_window' => ['required', 'regex:/^\d{2}:\d{2}-\d{2}:\d{2}$/'],
            'contact_channel' => ['required', Rule::enum(ToyotaServiceContactChannel::class)],
            'photo_asset_ids' => ['sometimes', 'array', 'max:5'],
            'photo_asset_ids.*' => [
                'uuid',
                'distinct',
                Rule::exists('assets', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('user_id', $userId)
                        ->where('category', 'toyota-service-photo')
                        ->where('is_protected', true)
                        ->where('status', 'active')
                        ->whereNull('deleted_at')
                ),
            ],
            'ths_address' => ['required_if:fulfillment_type,ths', 'nullable', 'string', 'min:10', 'max:2000'],
            'ths_city' => ['required_if:fulfillment_type,ths', 'nullable', 'string', 'max:100'],
            'ths_latitude' => ['required_if:fulfillment_type,ths', 'nullable', 'numeric', 'between:-90,90'],
            'ths_longitude' => ['required_if:fulfillment_type,ths', 'nullable', 'numeric', 'between:-180,180'],
            'ths_location_notes' => ['nullable', 'string', 'max:1000'],
            'source_appraisal_id' => ['nullable', 'uuid', 'exists:appraisals,id'],
            'source_bp_estimate_id' => [
                'prohibited',
            ],
            'campaign_source' => ['nullable', 'string', 'max:100'],
            'campaign_metadata' => ['nullable', 'array:utm_source,utm_medium,utm_campaign,utm_content,utm_term'],
            'campaign_metadata.*' => ['nullable', 'string', 'max:255'],
            'service_consent' => ['required', 'accepted'],
        ];
    }
}
