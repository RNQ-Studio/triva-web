<?php

namespace App\Http\Requests\Api\V1;

use App\Models\PartnerLogo;

/**
 * Pembaruan parsial lewat multipart `POST`, karena PHP tidak membaca body
 * multipart pada `PUT`/`PATCH`. Field yang dikirim kosong dikosongkan.
 */
class UpdatePartnerLogoRequest extends StorePartnerLogoRequest
{
    public function authorize(): bool
    {
        $logo = $this->route('partnerLogo');

        return $logo instanceof PartnerLogo
            && ($this->user()?->can('update', $logo) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'logo' => ['sometimes', 'required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'link_url' => ['sometimes', 'nullable', 'string', 'max:500', 'url:http,https'],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
