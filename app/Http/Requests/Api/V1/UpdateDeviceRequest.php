<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Enums\DevicePlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:255'],
            'platform' => ['required', 'string', Rule::enum(DevicePlatform::class)],
            'os_version' => ['sometimes', 'nullable', 'string', 'max:100'],
            'app_version' => ['sometimes', 'nullable', 'string', 'max:50'],
            'app_build' => ['sometimes', 'nullable', 'string', 'max:50'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'push_token' => ['present', 'nullable', 'string', 'max:500'],
        ];
    }
}
