<?php

namespace App\Http\Requests\Api\V1;

use App\Models\InfoPopup;
use Illuminate\Validation\Validator;

/**
 * Pembaruan parsial lewat multipart `POST`, karena PHP tidak membaca body
 * multipart pada `PUT`/`PATCH`. Field yang dikirim kosong dikosongkan.
 */
class UpdateInfoPopupRequest extends StoreInfoPopupRequest
{
    public function authorize(): bool
    {
        $popup = $this->popup();

        return $popup !== null && ($this->user()?->can('update', $popup) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'image' => ['sometimes', 'required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'button_label' => ['sometimes', 'nullable', 'string', 'max:40'],
            'button_url' => ['sometimes', 'nullable', 'string', 'max:500', 'url:http,https'],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0', 'max:9999'],
            'interval_hours' => ['sometimes', 'required', 'integer', 'min:1', 'max:720'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'starts_on' => ['sometimes', 'nullable', 'date'],
            'ends_on' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateButtonAndWindow($validator, $this->popup()),
        ];
    }

    private function popup(): ?InfoPopup
    {
        $popup = $this->route('infoPopup');

        return $popup instanceof InfoPopup ? $popup : null;
    }
}
