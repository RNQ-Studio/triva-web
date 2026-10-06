<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesAdminContent;
use App\Models\PartnerLogo;
use Illuminate\Foundation\Http\FormRequest;

class StorePartnerLogoRequest extends FormRequest
{
    use ValidatesAdminContent;

    public function authorize(): bool
    {
        return $this->user()?->can('create', PartnerLogo::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeBooleans(['is_active']);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'logo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'link_url' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'Nama mitra',
            'logo' => 'Logo mitra',
            'link_url' => 'Tautan mitra',
            'sort_order' => 'Urutan tampil',
            'is_active' => 'Status aktif',
        ];
    }
}
