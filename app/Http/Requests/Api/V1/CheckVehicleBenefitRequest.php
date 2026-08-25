<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CheckVehicleBenefitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Nomor rangka Toyota berjumlah 17 karakter, tetapi pelanggan
            // sering menyalinnya dengan spasi atau tanda hubung. Panjangnya
            // dilonggarkan dan normalisasi dilakukan di service.
            'vin' => ['required', 'string', 'min:5', 'max:32'],
            'year' => [
                'nullable',
                'integer',
                'min:1980',
                'max:'.((int) date('Y') + 1),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'vin.required' => 'Masukkan nomor rangka kendaraan Anda.',
        ];
    }
}
