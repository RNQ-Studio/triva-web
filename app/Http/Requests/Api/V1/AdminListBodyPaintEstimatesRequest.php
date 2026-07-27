<?php

namespace App\Http\Requests\Api\V1;

use App\Models\BodyPaintEstimate;
use App\Support\Enums\BodyPaintEstimateStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminListBodyPaintEstimatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'manageAny',
            BodyPaintEstimate::class,
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'nullable',
                Rule::enum(BodyPaintEstimateStatus::class),
            ],
            'service_location_id' => [
                'nullable',
                'uuid',
                'exists:toyota_service_locations,id',
            ],
            'estimator_id' => ['nullable', 'integer', 'exists:users,id'],
            'sla_status' => ['nullable', Rule::in(['due', 'overdue'])],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => [
                'nullable',
                Rule::in(['updated_desc', 'due_asc', 'submitted_desc']),
            ],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
