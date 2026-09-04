<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreditQuickSimulationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'program_id' => ['required', 'uuid', 'exists:credit_programs,id'],
            'otr_price' => ['required', 'integer', 'min:10000000', 'max:999999999999'],
            'dp_percent' => ['required', 'integer', Rule::in(config('credit_acc.dp_percent_options', [20, 25, 30]))],
            'tenor_years' => ['required', 'integer', Rule::in(config('credit_acc.tenor_years_options', [1, 2, 3, 4, 5]))],
            'campaign_source' => ['nullable', 'string', 'max:100'],
        ];
    }
}
