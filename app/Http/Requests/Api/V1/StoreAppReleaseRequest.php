<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppReleaseRequest extends FormRequest
{
    /**
     * Cocokkan header `X-App-Release-Key` dengan konfigurasi secara
     * timing-safe. Kunci kosong mematikan endpoint sepenuhnya, jadi tidak ada
     * bypass tak sengaja sebelum kunci di-set di server.
     *
     * Pemeriksaan ini sengaja berada di `authorize()`, bukan di controller,
     * supaya pemanggil tanpa kunci ditolak sebelum validasi — kalau tidak,
     * mereka menerima 422 yang membocorkan bentuk payload yang diharapkan.
     */
    public function authorize(): bool
    {
        $configured = (string) config('app_update.upload_key', '');
        if ($configured === '') {
            return false;
        }

        $provided = (string) $this->header('X-App-Release-Key', '');

        return $provided !== '' && hash_equals($configured, $provided);
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.config('app_update.max_kilobytes'),
            ],
            'platform' => ['nullable', 'string', 'in:android,ios'],
            'version_code' => ['required', 'integer', 'min:1'],
            'version_name' => ['required', 'string', 'max:50'],
            'release_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
