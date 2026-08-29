<?php

namespace App\Http\Requests\Api\V1;

use App\Models\CreditSimulation;
use App\Support\Enums\CreditSimulationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminListCreditSimulationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'manageAny',
            CreditSimulation::class,
        ) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(CreditSimulationStatus::class)],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['saved_desc', 'updated_desc'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
