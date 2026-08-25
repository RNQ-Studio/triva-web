<?php

namespace App\Services;

use App\Models\Appraisal;
use App\Models\CreditProgram;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Menyusun dua opsi unit baru Toyota yang uang mukanya tertutup oleh harga
 * appraisal pelanggan.
 *
 * Notulensi 19 Agustus 2026 mengganti rentang harga dan data pembanding dengan
 * "simulasi 2 opsi kendaraan unit baru Toyota dengan menyesuaikan harga
 * appraisal sebagai DP unit baru (1 tingkat di atas unit pelanggan)", memakai
 * hitungan reguler. Yang dipilih adalah program termurah yang harganya masih di
 * atas nilai unit pelanggan dan DP minimumnya sudah tertutup hasil appraisal --
 * jadi pelanggan tidak ditawari unit yang uang mukanya belum tentu terjangkau.
 */
class AppraisalUpgradeOptionService
{
    public function __construct(
        private readonly CreditSimulationCalculator $calculator,
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
        // halaman hasil, bukan turunan lain, supaya DP di pop-up tidak berbeda
        // dari harga yang dijanjikan.
        $tradeInValue = $result->trade_in_high;
        $options = [];

        foreach ($this->candidates($appraisal, $tradeInValue) as $program) {
            $option = $this->option($program, $appraisal, $user);
            if ($option !== null) {
                $options[] = $option;
            }

            if (count($options) === 2) {
                break;
            }
        }

        return [
            'trade_in_value' => $tradeInValue,
            'options' => $options,
        ];
    }

    /**
     * Kandidat diurutkan dari yang paling dekat harganya, sehingga dua yang
     * terpilih benar-benar satu tingkat di atas unit pelanggan dan bukan
     * lompatan kelas yang tidak relevan.
     *
     * @return Collection<int, CreditProgram>
     */
    private function candidates(Appraisal $appraisal, int $tradeInValue)
    {
        return CreditProgram::query()
            ->effective()
            ->where('otr_price', '>', $tradeInValue)
            ->when(
                filled($appraisal->vehicle?->city),
                fn ($query) => $query->where('city', $appraisal->vehicle->city),
            )
            ->orderBy('otr_price')
            ->orderBy('program_code')
            ->limit(12)
            ->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function option(
        CreditProgram $program,
        Appraisal $appraisal,
        User $user,
    ): ?array {
        $tenor = $this->longestTenor($program);
        if ($tenor === null) {
            return null;
        }

        try {
            $calculation = $this->calculator->calculate($user, $program, [
                'otr_price' => $program->otr_price,
                'cash_down_payment' => 0,
                'trade_in_appraisal_id' => $appraisal->getKey(),
                'manual_trade_in_value' => 0,
                'use_trade_in_as_dp' => true,
                'old_vehicle_payoff' => 0,
                'tenor_months' => $tenor,
                'accept_expired_appraisal' => true,
            ]);
        } catch (ValidationException) {
            // Uang muka dari appraisal belum menutup batas program ini, atau
            // justru melewati batas atasnya. Unit berikutnya yang dicoba.
            return null;
        }

        return [
            'program_id' => $program->getKey(),
            'program_code' => $program->program_code,
            'package_code' => $program->package_code,
            'partner_name' => $program->partner_name,
            'program_name' => $program->program_name,
            'vehicle_model' => $program->vehicle_model,
            'vehicle_variant' => $program->vehicle_variant,
            'model_year' => $program->model_year,
            'otr_price' => $program->otr_price,
            'tenor_months' => $tenor,
            'down_payment_from_appraisal' => $calculation['calculation']['total_down_payment'],
            'monthly_installment' => $calculation['calculation']['monthly_installment'],
            'is_demo' => $program->is_demo,
        ];
    }

    private function longestTenor(CreditProgram $program): ?int
    {
        $months = collect($program->tenor_options)
            ->map(fn (array $option): int => (int) ($option['tenor_months'] ?? 0))
            ->filter(fn (int $value): bool => $value > 0)
            ->max();

        return $months === null ? null : (int) $months;
    }
}
