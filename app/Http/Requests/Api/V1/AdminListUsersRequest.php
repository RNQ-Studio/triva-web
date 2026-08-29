<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use App\Support\Enums\Gender;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminListUsersRequest extends FormRequest
{
    /** Kolom yang boleh dipakai untuk mengurutkan daftar. */
    public const SORTS = ['name', 'created_at', 'email'];

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) ?? false;
    }

    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'gender' => ['sometimes', 'string', Rule::in([...Gender::values(), 'unknown'])],
            'is_active' => ['sometimes', 'boolean'],
            'has_demographics' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'string', Rule::in(self::SORTS)],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
        ];
    }
}
