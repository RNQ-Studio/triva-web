<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesAdminContent;
use App\Models\InfoPopup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class StoreInfoPopupRequest extends FormRequest
{
    use ValidatesAdminContent;

    public function authorize(): bool
    {
        return $this->user()?->can('create', InfoPopup::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeBooleans(['is_active']);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'image' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'button_label' => ['nullable', 'string', 'max:40'],
            'button_url' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'interval_hours' => ['sometimes', 'integer', 'min:1', 'max:720'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => 'Judul',
            'image' => 'Gambar popup',
            'button_label' => 'Label tombol',
            'button_url' => 'Tautan tombol',
            'sort_order' => 'Urutan tampil',
            'interval_hours' => 'Tampil setiap (jam)',
            'is_active' => 'Status aktif',
            'starts_on' => 'Mulai tayang',
            'ends_on' => 'Berakhir',
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateButtonAndWindow($validator),
        ];
    }

    protected function validateButtonAndWindow(Validator $validator, ?InfoPopup $current = null): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $label = $this->has('button_label') ? $this->input('button_label') : $current?->button_label;
        $url = $this->has('button_url') ? $this->input('button_url') : $current?->button_url;
        if (filled($label) && blank($url)) {
            $validator->errors()->add('button_url', 'Isi tautan tombol, atau kosongkan label tombol.');
        }
        if (filled($url) && blank($label)) {
            $validator->errors()->add('button_label', 'Isi label tombol, atau kosongkan tautan tombol.');
        }

        $startsOn = $this->has('starts_on') ? $this->input('starts_on') : $current?->starts_on?->toDateString();
        $endsOn = $this->has('ends_on') ? $this->input('ends_on') : $current?->ends_on?->toDateString();
        if (filled($startsOn) && filled($endsOn)
            && Carbon::parse($endsOn)->lt(Carbon::parse($startsOn))) {
            $validator->errors()->add('ends_on', 'Tanggal berakhir tidak boleh sebelum tanggal mulai tayang.');
        }
    }
}
