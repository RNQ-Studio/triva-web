<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Enums\ToyotaServiceFulfillmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ToyotaServiceAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
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
            'from_date' => ['sometimes', 'date_format:Y-m-d'],
            'days' => ['sometimes', 'integer', 'min:1', 'max:31'],
            'city' => ['nullable', 'required_with:latitude,longitude', 'string', 'max:100'],
            'latitude' => ['nullable', 'required_with:city,longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:city,latitude', 'numeric', 'between:-180,180'],
        ];
    }
}
