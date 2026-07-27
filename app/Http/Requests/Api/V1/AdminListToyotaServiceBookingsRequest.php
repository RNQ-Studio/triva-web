<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Enums\ToyotaServiceBookingStatus;
use App\Support\Enums\ToyotaServiceFulfillmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminListToyotaServiceBookingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('service_bookings.viewAny') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(ToyotaServiceBookingStatus::class)],
            'fulfillment_type' => ['sometimes', Rule::enum(ToyotaServiceFulfillmentType::class)],
            'service_location_id' => ['sometimes', 'uuid', 'exists:toyota_service_locations,id'],
            'service_type_id' => ['sometimes', 'uuid', 'exists:toyota_service_types,id'],
            'advisor_id' => ['sometimes', 'integer', 'exists:users,id'],
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'sla_status' => ['sometimes', Rule::in(['overdue', 'within_sla'])],
            'search' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', Rule::in(['updated_desc', 'due_asc', 'slot_asc'])],
        ];
    }
}
