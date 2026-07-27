<?php

namespace App\Http\Requests\Api\V1;

use App\Models\OtoxpertBooking;
use App\Support\Enums\OtoxpertBookingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminListOtoxpertBookingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageAny', OtoxpertBooking::class) === true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(OtoxpertBookingStatus::class)],
            'workshop_id' => [
                'nullable',
                'uuid',
                'exists:otoxpert_workshops,id',
            ],
            'service_id' => [
                'nullable',
                'uuid',
                'exists:otoxpert_services,id',
            ],
            'operator_id' => ['nullable', 'integer', 'exists:users,id'],
            'sla_status' => ['nullable', Rule::in(['all', 'due', 'overdue'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => [
                'nullable',
                Rule::in(['updated_desc', 'due_asc', 'slot_asc']),
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
