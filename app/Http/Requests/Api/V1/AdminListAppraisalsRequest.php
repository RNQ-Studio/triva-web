<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Appraisal;
use App\Support\Enums\AppraisalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminListAppraisalsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageAny', Appraisal::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(AppraisalStatus::class)],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => [
                'nullable',
                Rule::in(['updated_desc', 'submitted_desc', 'created_desc']),
            ],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
