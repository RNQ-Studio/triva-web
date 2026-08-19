<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Enums\VisitSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'visit_id' => ['required', 'uuid'],
            'source' => ['required', 'string', Rule::in(VisitSource::clientValues())],
            'app_version' => ['sometimes', 'nullable', 'string', 'max:50'],
            'app_build' => ['sometimes', 'nullable', 'string', 'max:50'],
            'occurred_at' => ['prohibited'],
        ];
    }
}
