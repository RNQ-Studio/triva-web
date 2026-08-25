<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Enums\AppraisalDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppraisalDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $appraisal = $this->route('appraisal');

        return $appraisal !== null
            && ($this->user()?->can('decide', $appraisal) ?? false);
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(AppraisalDecision::class)],
            // Harapan harga hanya bermakna saat pelanggan menolak angka yang
            // ditawarkan; batas atasnya menahan salah ketik nol berlebih.
            // Tidak diwajibkan di sisi server karena pemasangan lama belum
            // mengenal isian ini; aplikasi versi baru selalu menanyakannya
            // sebelum mengirim penolakan.
            'expected_price' => [
                'nullable',
                'integer',
                'min:1000000',
                'max:100000000000',
            ],
        ];
    }
}
