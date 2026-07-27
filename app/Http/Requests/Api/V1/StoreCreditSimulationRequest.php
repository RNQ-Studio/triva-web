<?php

namespace App\Http\Requests\Api\V1;

class StoreCreditSimulationRequest extends CreditSimulationInputRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'idempotency_key' => ['required', 'uuid'],
            'comparison_group_id' => ['nullable', 'uuid'],
            'campaign_source' => ['nullable', 'string', 'max:100'],
        ];
    }
}
