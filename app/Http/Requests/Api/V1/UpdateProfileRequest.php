<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()?->getKey()),
            ],
            'phone' => ['sometimes', 'required', 'regex:/^\+?[0-9]{9,15}$/'],
            'city' => ['sometimes', 'required', 'string', 'max:100'],
            'service_consent' => ['sometimes', 'accepted'],
            'marketing_consent' => ['sometimes', 'boolean'],
        ];
    }
}
