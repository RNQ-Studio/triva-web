<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Enums\VisitSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordMenuUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Bukan Rule::enum: aplikasi yang lebih baru boleh mengirim menu
            // yang belum dikenal server tanpa ditolak 422.
            'menu_key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'source' => ['required', 'string', Rule::in(VisitSource::clientValues())],
            'app_version' => ['sometimes', 'nullable', 'string', 'max:50'],
            'app_build' => ['sometimes', 'nullable', 'string', 'max:50'],
            'occurred_at' => ['prohibited'],
        ];
    }
}
