<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OtoxpertAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'workshop_id' => [
                'required',
                'uuid',
                Rule::exists('otoxpert_workshops', 'id')->where(
                    'is_active',
                    true,
                ),
            ],
            'service_id' => [
                'required',
                'uuid',
                Rule::exists('otoxpert_services', 'id')->where(
                    'is_active',
                    true,
                ),
            ],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer', 'min:1', 'max:31'],
        ];
    }
}
