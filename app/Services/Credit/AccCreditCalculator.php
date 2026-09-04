<?php

namespace App\Services\Credit;

use Illuminate\Validation\ValidationException;

/**
 * Perhitungan simulasi kredit ACC mengikuti lembar kerja cabang
 * (revisi 4 September 2026).
 *
 *   DP murni       = OTR x %DP (dibulatkan ke ribuan)
 *   Pokok hutang   = OTR - DP murni
 *   Asuransi       = OTR x rate asuransi (all risk th-1 + TLO), ke ribuan
 *   Pokok dibiayai = pokok hutang + asuransi
 *   Bunga          = pokok dibiayai x bunga flat/tahun x tenor tahun
 *   Angsuran       = (pokok dibiayai + bunga) / (tenor x 12), ke ribuan
 *   Total DP (TDP) = DP murni + angsuran pertama + administrasi + TJH/polis
 *
 * Contoh lembar kerja: OTR 183.260.000, DP 52.596.000, tenor 5 tahun, bunga
 * 5,80%, asuransi 5,36% -> angsuran 3.020.000 dan TDP 57.016.000.
 */
class AccCreditCalculator
{
    /**
     * @return array<string, mixed>
     */
    public function quote(
        int $otrPrice,
        int $dpPercent,
        int $tenorYears,
        string $vehicleModel,
    ): array {
        $this->assertSupported($dpPercent, $tenorYears, $otrPrice);
        $class = $this->vehicleClass($vehicleModel);
        $rateBps = $this->interestRateBps($class, $dpPercent, $tenorYears);
        $insuranceBps = $this->insuranceRateBps($otrPrice, $tenorYears);
        $downPayment = $this->roundToUnit($this->ratio($otrPrice * $dpPercent, 100));

        return $this->quoteWithRates(
            otrPrice: $otrPrice,
            downPayment: $downPayment,
            tenorYears: $tenorYears,
            interestRateBps: $rateBps,
            insuranceRateBps: $insuranceBps,
            extra: [
                'dp_percent' => $dpPercent,
                'vehicle_class' => $class,
            ],
        );
    }

    /**
     * Perhitungan dengan DP nominal (mis. harga appraisal sebagai DP) dan
     * rate yang sudah ditentukan.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function quoteWithRates(
        int $otrPrice,
        int $downPayment,
        int $tenorYears,
        int $interestRateBps,
        int $insuranceRateBps,
        array $extra = [],
    ): array {
        $downPayment = max(0, min($downPayment, $otrPrice));
        $principal = $otrPrice - $downPayment;
        $insurance = $this->roundToUnit($this->ratio($otrPrice * $insuranceRateBps, 10_000));
        $financed = $principal + $insurance;
        $tenorMonths = $tenorYears * 12;
        $interest = $this->ratio($financed * $interestRateBps * $tenorYears, 10_000);
        $totalFinanced = $financed + $interest;
        $installment = $tenorMonths === 0
            ? 0
            : $this->roundToUnit($this->ratio($totalFinanced, $tenorMonths));
        $adminFee = (int) config('credit_acc.administration_fee');
        $liabilityFee = (int) config('credit_acc.liability_insurance_fee');
        $totalDownPayment = $downPayment + $installment + $adminFee + $liabilityFee;
        $totalPayment = $totalDownPayment + ($installment * max($tenorMonths - 1, 0));

        return [
            'formula_version' => (string) config('credit_acc.formula_version'),
            'otr_price' => $otrPrice,
            'down_payment' => $downPayment,
            'down_payment_percent' => $otrPrice === 0
                ? 0.0
                : round($downPayment / $otrPrice * 100, 2),
            'principal' => $principal,
            'insurance_rate_basis_points' => $insuranceRateBps,
            'insurance_premium' => $insurance,
            'financed_amount' => $financed,
            'annual_flat_rate_basis_points' => $interestRateBps,
            'tenor_years' => $tenorYears,
            'tenor_months' => $tenorMonths,
            'total_flat_interest' => $interest,
            'monthly_installment' => $installment,
            'administration_fee' => $adminFee,
            'liability_insurance_fee' => $liabilityFee,
            'first_installment' => $installment,
            'total_down_payment' => $totalDownPayment,
            'total_payment' => $totalPayment,
            'is_estimate' => true,
            'disclaimer' => 'Hasil simulasi bersifat estimasi dan bukan persetujuan kredit. Nilai final mengikuti verifikasi sales dan partner pembiayaan.',
            ...$extra,
        ];
    }

    public function vehicleClass(string $vehicleModel): string
    {
        $needle = strtolower(trim($vehicleModel));
        /** @var array<string, list<string>> $classes */
        $classes = config('credit_acc.vehicle_classes', []);
        foreach ($classes as $class => $keywords) {
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && str_contains($needle, $keyword)) {
                    return $class;
                }
            }
        }

        return (string) config('credit_acc.default_vehicle_class', 'reg2');
    }

    public function interestRateBps(string $vehicleClass, int $dpPercent, int $tenorYears): int
    {
        $tier = $dpPercent >= 30 ? '30' : '25';
        $rates = config("credit_acc.interest_rates.{$vehicleClass}.{$tier}");
        if (! is_array($rates) || ! isset($rates[$tenorYears])) {
            throw ValidationException::withMessages([
                'tenor_years' => ['Bunga untuk kombinasi kelas kendaraan, DP, dan tenor ini belum tersedia.'],
            ]);
        }

        return (int) $rates[$tenorYears];
    }

    public function insuranceRateBps(int $otrPrice, int $tenorYears): int
    {
        /** @var list<array{max: int|null, rates: array<int, int>}> $bands */
        $bands = config('credit_acc.insurance_rates', []);
        foreach ($bands as $band) {
            if ($band['max'] === null || $otrPrice <= $band['max']) {
                if (! isset($band['rates'][$tenorYears])) {
                    break;
                }

                return (int) $band['rates'][$tenorYears];
            }
        }

        throw ValidationException::withMessages([
            'otr_price' => ['Rate asuransi untuk harga OTR dan tenor ini belum tersedia.'],
        ]);
    }

    /** @return array<string, mixed> */
    public function rateCard(): array
    {
        return [
            'formula_version' => config('credit_acc.formula_version'),
            'dp_percent_options' => config('credit_acc.dp_percent_options'),
            'tenor_years_options' => config('credit_acc.tenor_years_options'),
            'administration_fee' => (int) config('credit_acc.administration_fee'),
            'liability_insurance_fee' => (int) config('credit_acc.liability_insurance_fee'),
            'rounding' => (int) config('credit_acc.rounding'),
            'default_vehicle_class' => config('credit_acc.default_vehicle_class'),
            'vehicle_classes' => config('credit_acc.vehicle_classes'),
            'interest_rates' => config('credit_acc.interest_rates'),
            'insurance_rates' => config('credit_acc.insurance_rates'),
        ];
    }

    private function assertSupported(int $dpPercent, int $tenorYears, int $otrPrice): void
    {
        if (! in_array($dpPercent, config('credit_acc.dp_percent_options', []), true)) {
            throw ValidationException::withMessages([
                'dp_percent' => ['Pilihan DP tidak tersedia.'],
            ]);
        }
        if (! in_array($tenorYears, config('credit_acc.tenor_years_options', []), true)) {
            throw ValidationException::withMessages([
                'tenor_years' => ['Pilihan tenor tidak tersedia.'],
            ]);
        }
        if ($otrPrice <= 0) {
            throw ValidationException::withMessages([
                'otr_price' => ['Harga OTR harus lebih dari nol.'],
            ]);
        }
    }

    private function ratio(int $numerator, int $denominator): int
    {
        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }

    private function roundToUnit(int $value): int
    {
        $unit = max(1, (int) config('credit_acc.rounding', 1_000));

        return intdiv($value + intdiv($unit, 2), $unit) * $unit;
    }
}
