<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Vehicle;
use App\Support\Enums\ToyotaServiceContactChannel;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreOtoxpertBookingRequest extends FormRequest
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

        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()?->getKey();

        return [
            'idempotency_key' => ['required', 'uuid'],
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'workshop_id' => [
                'required',
                'uuid',
                Rule::exists('otoxpert_workshops', 'id')->where(
                    'is_active',
                    true,
                ),
            ],
            'service_id' => [
                'required',
                'uuid',
                Rule::exists('otoxpert_services', 'id')->where(
                    'is_active',
                    true,
                ),
            ],
            'current_mileage' => [
                'required',
                'integer',
                'min:0',
                'max:5000000',
            ],
            'last_service_date' => [
                'nullable',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],
            'complaint' => ['required', 'string', 'min:5', 'max:3000'],
            'symptom_codes' => ['required', 'array', 'min:1', 'max:6'],
            'symptom_codes.*' => [
                'string',
                'distinct',
                Rule::in([
                    'noise',
                    'vibration',
                    'warning_light',
                    'leak',
                    'performance',
                    'other',
                ]),
            ],
            'primary_slot' => ['required', 'array:date,time_window'],
            'primary_slot.date' => ['required', 'date_format:Y-m-d'],
            'primary_slot.time_window' => [
                'required',
                'regex:/^\d{2}:\d{2}-\d{2}:\d{2}$/',
            ],
            'alternative_slot' => ['required', 'array:date,time_window'],
            'alternative_slot.date' => ['required', 'date_format:Y-m-d'],
            'alternative_slot.time_window' => [
                'required',
                'regex:/^\d{2}:\d{2}-\d{2}:\d{2}$/',
            ],
            'pickup_delivery_requested' => ['sometimes', 'boolean'],
            'contact_channel' => [
                'required',
                Rule::enum(ToyotaServiceContactChannel::class),
            ],
            'photo_asset_ids' => ['sometimes', 'array', 'max:5'],
            'photo_asset_ids.*' => [
                'uuid',
                'distinct',
                Rule::exists('assets', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('user_id', $userId)
                        ->where('category', 'otoxpert-booking-photo')
                        ->where('is_protected', true)
                        ->where('status', 'active')
                        ->whereNull('deleted_at')
                ),
            ],
            'partner_consent' => ['accepted'],
            'partner_consent_version' => [
                'required',
                'string',
                Rule::in(['otoxpert-data-sharing-v1']),
            ],
            'campaign_source' => ['nullable', 'string', 'max:100'],
            'campaign_metadata' => ['nullable', 'array'],
            'campaign_metadata.*' => ['nullable', 'scalar'],
        ];
    }
}
