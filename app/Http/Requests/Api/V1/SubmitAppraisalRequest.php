<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAppraisalRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    public function authorize(): bool
    {
        $appraisal = $this->route('appraisal');

        return $appraisal !== null
            && $appraisal->user_id === $this->user()?->getKey();
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'service_consent' => ['required', 'accepted'],
            'marketing_consent' => ['sometimes', 'boolean'],
        ];
    }
}
