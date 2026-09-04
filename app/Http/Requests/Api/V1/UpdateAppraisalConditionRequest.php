<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppraisalConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $appraisal = $this->route('appraisal');

        return $appraisal !== null
            && ($this->user()?->can('update', $appraisal) ?? false);
    }

    public function rules(): array
    {
        return [
            'tax_status' => ['required', Rule::in(['active', 'overdue', 'unknown'])],
            'flood_history' => ['required', Rule::in(['yes', 'no', 'unknown'])],
            'major_accident_history' => ['required', Rule::in(['yes', 'no', 'unknown'])],
            // Revisi 4 September 2026 menyederhanakan pilihan menjadi bengkel
            // authorized/umum dan tangan pertama/kedua-atau-lebih. Nilai lama
            // tetap diterima karena pemasangan lama masih mengirimkannya.
            'service_history' => ['required', Rule::in(['authorized', 'general', 'complete', 'partial', 'none', 'unknown'])],
            'ownership' => ['required', Rule::in(['first', 'second_or_more', 'second', 'more', 'unknown'])],
            // Aplikasi tidak punya gerbang paksa-perbarui, sehingga pemasangan
            // lama masih mengirim payload tanpa tiga isian ini. Nilainya
            // diterima opsional -- aplikasi versi baru sendiri yang mewajibkan
            // pelanggan mengisinya sebelum melanjutkan -- supaya pengguna lama
            // tidak terjebak 422 di tengah alur appraisal.
            'condition_grade' => ['sometimes', 'nullable', Rule::in(['a', 'b', 'c', 'd'])],
            'engine_condition' => ['sometimes', 'nullable', Rule::in(['normal', 'wet'])],
            'tyre_condition' => ['sometimes', 'nullable', Rule::in(['normal', 'damaged'])],
        ];
    }
}
