<?php

namespace App\Services;

use App\Models\Appraisal;
use App\Models\CreditProgram;
use App\Models\User;
use App\Services\Credit\AccCreditCalculator;
use Illuminate\Support\Collection;

/**
 * Dua unit baru Toyota yang ditawarkan di halaman hasil appraisal, dengan harga
 * appraisal sebagai uang muka.
 *
 * Revisi 4 September 2026 menetapkan unitnya berdasarkan rentang harga
 * appraisal (>150 juta: Veloz Hybrid & Zenix Hybrid; 100-150 juta: Veloz
 * Hybrid & Reborn; <100 juta: Raize & Veloz Hybrid) -- Veloz Hybrid selalu
 * ada -- dan angsurannya dihitung dengan rate card ACC tenor 5 tahun. Unit
 * dicari lewat `credit_programs.unit_key`; unit yang belum dikonfigurasi
 * cabang dilewati alih-alih diganti unit lain.
 */
class AppraisalUpgradeOptionService
{
    public const DEFAULT_TENOR_YEARS = 5;

    public function __construct(
        private readonly AccCreditCalculator $calculator,
    ) {}

    /**
     * @return array{
     *     trade_in_value: int,
     *     options: list<array<string, mixed>>
     * }
     */
    public function for(Appraisal $appraisal, User $user): array
    {
        $appraisal->loadMissing(['latestResult', 'vehicle']);
        $result = $appraisal->latestResult;
        if ($result === null) {
            return ['trade_in_value' => 0, 'options' => []];
        }

        // Angka yang dipakai adalah harga yang barusan dilihat pelanggan pada
        // halaman hasil, supaya DP di kartu tidak berbeda dari harga yang
        // dijanjikan.
        $tradeInValue = $result->trade_in_high;
        $programs = $this->programs($this->unitKeysFor($tradeInValue));

        $options = [];
        foreach ($programs as $program) {
            $options[] = $this->option($program, $tradeInValue);
        }
        // Harga bawah dulu, lalu harga atas.
        usort($options, fn (array $a, array $b): int => $a['otr_price'] <=> $b['otr_price']);

        return [
            'trade_in_value' => $tradeInValue,
            'options' => $options,
        ];
    }

    /** @return list<string> */
    public function unitKeysFor(int $tradeInValue): array
    {
        /** @var list<array{min: int, units: list<string>}> $rules */
        $rules = config('credit_acc.appraisal_recommendations', []);
        usort($rules, fn (array $a, array $b): int => $b['min'] <=> $a['min']);
        foreach ($rules as $rule) {
            if ($tradeInValue >= $rule['min']) {
                return $rule['units'];
            }
        }

        return [];
    }

    /**
     * @param  list<string>  $unitKeys
     * @return Collection<int, CreditProgram>
     */
    private function programs(array $unitKeys): Collection
    {
        if ($unitKeys === []) {
            return new Collection;
        }

        $programs = CreditProgram::query()
            ->effective()
            ->whereIn('unit_key', $unitKeys)
            ->orderByDesc('version')
            ->get();

        // Satu program per unit (versi tertinggi), diurutkan sesuai aturan.
        return collect($unitKeys)
            ->map(fn (string $key): ?CreditProgram => $programs->firstWhere('unit_key', $key))
            ->filter()
            ->values();
    }

    /** @return array<string, mixed> */
    private function option(CreditProgram $program, int $tradeInValue): array
    {
        $tenorYears = self::DEFAULT_TENOR_YEARS;
        $downPayment = min($tradeInValue, $program->otr_price);
        $dpPercent = $program->otr_price === 0
            ? 0
            : intdiv($downPayment * 100, $program->otr_price);
        $quote = $this->calculator->quoteWithRates(
            otrPrice: $program->otr_price,
            downPayment: $downPayment,
            tenorYears: $tenorYears,
            interestRateBps: $this->calculator->interestRateBps(
                $this->calculator->vehicleClass($program->vehicle_model),
                $dpPercent,
                $tenorYears,
            ),
            insuranceRateBps: $this->calculator->insuranceRateBps(
                $program->otr_price,
                $tenorYears,
            ),
        );

        return [
            'program_id' => $program->getKey(),
            'program_code' => $program->program_code,
            'package_code' => $program->package_code,
            'unit_key' => $program->unit_key,
            'image_url' => $program->imageUrl(),
            'partner_name' => $program->partner_name,
            'program_name' => $program->program_name,
            'vehicle_model' => $program->vehicle_model,
            'vehicle_variant' => $program->vehicle_variant,
            'model_year' => $program->model_year,
            'otr_price' => $program->otr_price,
            'tenor_months' => $quote['tenor_months'],
            'down_payment_from_appraisal' => $quote['down_payment'],
            'monthly_installment' => $quote['monthly_installment'],
            'annual_flat_rate_basis_points' => $quote['annual_flat_rate_basis_points'],
            'is_demo' => $program->is_demo,
        ];
    }
}
