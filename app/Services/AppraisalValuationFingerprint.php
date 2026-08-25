<?php

namespace App\Services;

use App\Models\Appraisal;
use Illuminate\Support\Str;

/**
 * Menghitung sidik jari penilaian dari data yang benar-benar memengaruhi harga.
 *
 * Notulensi 19 Agustus 2026 mencatat "harga yang muncul berbeda antar pengguna
 * account yang berbeda padahal data yang dimasukkan sama". Penyebabnya adalah
 * hasil scraping pasar dan keputusan model yang tidak deterministik. Sidik jari
 * ini sengaja tidak memuat identitas pemilik supaya hasil yang sudah dihitung
 * bisa dipakai ulang lintas akun.
 */
class AppraisalValuationFingerprint
{
    public function for(Appraisal $appraisal): string
    {
        $appraisal->loadMissing('vehicle');
        $vehicle = $appraisal->vehicle;

        return hash('sha256', implode('|', [
            'policy:'.$this->policyVersion(),
            $this->normalize($vehicle->make),
            $this->normalize($vehicle->model),
            $this->normalize($vehicle->variant),
            (string) $vehicle->year,
            $this->normalize($vehicle->transmission),
            $this->normalize($vehicle->fuel_type),
            (string) $vehicle->mileage,
            $this->normalize($vehicle->city),
            $this->normalize((string) ($appraisal->tax_status ?? '')),
            $this->normalize((string) ($appraisal->flood_history ?? '')),
            $this->normalize((string) ($appraisal->major_accident_history ?? '')),
            $this->normalize((string) ($appraisal->service_history ?? '')),
            $this->normalize((string) ($appraisal->ownership ?? '')),
            $this->normalize((string) ($appraisal->condition_grade ?? '')),
            $this->normalize((string) ($appraisal->engine_condition ?? '')),
            $this->normalize((string) ($appraisal->tyre_condition ?? '')),
        ]));
    }

    /**
     * Setiap perubahan kebijakan potongan harus membuat sidik jari lama basi,
     * supaya koreksi harga baru tidak ditimpa hasil lama yang dipakai ulang.
     */
    private function policyVersion(): string
    {
        return implode(':', [
            (string) config('appraisal.market_data.dealer_margin_percent'),
            (string) config('appraisal.market_data.market_correction_percent'),
            (string) config('appraisal.market_data.diesel_market_correction_percent'),
            (string) config('appraisal.market_data.maximum_total_deduction_percent'),
            json_encode(config('appraisal.market_data.condition_grade_percent')) ?: '',
            (string) config('appraisal.market_data.rounding'),
        ]);
    }

    private function normalize(string $value): string
    {
        return (string) Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '');
    }
}
