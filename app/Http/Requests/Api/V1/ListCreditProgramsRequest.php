<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ListCreditProgramsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'city' => ['nullable', 'string', 'max:100'],
            'vehicle_model' => ['nullable', 'string', 'max:255'],
            'vehicle_variant' => ['nullable', 'string', 'max:255'],
            'model_year' => [
                'nullable',
                'integer',
                'min:1980',
                'max:'.(now('Asia/Jakarta')->year + 2),
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
