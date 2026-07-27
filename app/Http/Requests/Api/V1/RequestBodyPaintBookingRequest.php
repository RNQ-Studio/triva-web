<?php

namespace App\Http\Requests\Api\V1;

use App\Models\BodyPaintEstimate;
use App\Support\Enums\ToyotaServiceContactChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestBodyPaintBookingRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    public function authorize(): bool
    {
        $estimate = $this->route('estimate');

        return $estimate instanceof BodyPaintEstimate
            && ($this->user()?->can('requestBooking', $estimate) ?? false);
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'service_location_id' => [
                'required',
                'uuid',
                Rule::exists('toyota_service_locations', 'id')
                    ->where('is_active', true)
                    ->where('supports_workshop', true),
            ],
            'current_mileage' => [
                'required',
                'integer',
                'min:0',
                'max:5000000',
            ],
            'complaint' => ['required', 'string', 'min:5', 'max:3000'],
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
            'contact_channel' => [
                'required',
                Rule::enum(ToyotaServiceContactChannel::class),
            ],
            'service_consent' => ['required', 'accepted'],
        ];
    }
}
