<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppReleaseRequest extends FormRequest
{
    /**
     * Otorisasi dipegang controller lewat header `X-App-Release-Key`; request
     * ini hanya bertugas memvalidasi bentuk payload.
     */
    public function authorize(): bool
    {
        return true;
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
