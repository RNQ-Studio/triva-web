<?php

namespace App\Services;

use App\Models\Appraisal;
use App\Models\CreditProgram;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CreditSimulationCalculator
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function calculate(
        User $user,
        CreditProgram $program,
        array $data,
    ): array {
        $this->validateProgram($program, $data);
        if (filled($data['trade_in_appraisal_id'] ?? null)
            && (int) ($data['manual_trade_in_value'] ?? 0) > 0) {
            throw ValidationException::withMessages([
                'manual_trade_in_value' => [
                    'Pilih hasil appraisal atau nilai trade-in manual, bukan keduanya.',
                ],
            ]);
        }
        $tenor = $program->tenorOption((int) $data['tenor_months']);
        if ($tenor === null) {
            throw ValidationException::withMessages([
                'tenor_months' => [
                    'Tenor tidak tersedia pada program yang dipilih.',
                ],
            ]);
        }

        [$appraisal, $tradeInValue, $warnings] = $this->tradeIn(
            $user,
            $data,
        );
        $useTradeIn = (bool) $data['use_trade_in_as_dp'];
        if ($useTradeIn && $tradeInValue <= 0) {
            throw ValidationException::withMessages([
                'use_trade_in_as_dp' => [
                    'Masukkan nilai trade-in atau pilih hasil appraisal.',
                ],
            ]);
        }

        $oldPayoff = (int) $data['old_vehicle_payoff'];
        $tradeInEquity = max($tradeInValue - $oldPayoff, 0);
        $cashDownPayment = (int) $data['cash_down_payment'];
        $totalDownPayment = $cashDownPayment
            + ($useTradeIn ? $tradeInEquity : 0)
            + $program->approved_discount;
        $minimumDownPayment = $this->roundUpRatio(
            $program->otr_price,
            $program->minimum_dp_basis_points,
            10000,
        );
        $maximumDownPayment = intdiv(
            $program->otr_price * $program->maximum_dp_basis_points,
            10000,
        );
        if ($totalDownPayment < $minimumDownPayment) {
            throw ValidationException::withMessages([
                'cash_down_payment' => [
                    'Total uang muka minimum program adalah Rp '
                    .number_format($minimumDownPayment, 0, ',', '.').'.',
                ],
            ]);
        }
        if ($totalDownPayment > $maximumDownPayment
            || $totalDownPayment > $program->otr_price) {
            throw ValidationException::withMessages([
                'cash_down_payment' => [
                    'Total uang muka melebihi batas program.',
                ],
            ]);
        }

        $principal = $program->otr_price - $totalDownPayment;
        $rateBasisPoints = (int) $tenor['annual_flat_rate_basis_points'];
        $tenorMonths = (int) $tenor['tenor_months'];
        $totalFlatInterest = $this->roundRatio(
            $principal * $rateBasisPoints * $tenorMonths,
            10000 * 12,
        );
        $monthlyInstallment = $this->roundRatio(
            $principal + $totalFlatInterest,
            $tenorMonths,
        );
        $administrationFee = (int) ($tenor['administration_fee'] ?? 0);
        $provisionFee = (int) ($tenor['provision_fee'] ?? 0);
        $upfrontInsurance = (int) ($tenor['upfront_insurance'] ?? 0);
        $otherUpfrontCosts = (int) ($tenor['other_upfront_costs'] ?? 0);
        $initialPayment = $cashDownPayment
            + $administrationFee
            + $provisionFee
            + $upfrontInsurance
            + $otherUpfrontCosts;
        $totalPayment = $initialPayment
            + ($monthlyInstallment * $tenorMonths);

        return [
            'program' => $this->programSnapshot($program, $tenor),
            'inputs' => [
                'otr_price' => $program->otr_price,
                'cash_down_payment' => $cashDownPayment,
                'trade_in_appraisal_id' => $appraisal?->getKey(),
                'trade_in_value' => $tradeInValue,
                'use_trade_in_as_dp' => $useTradeIn,
                'old_vehicle_payoff' => $oldPayoff,
                'tenor_months' => $tenorMonths,
            ],
            'calculation' => [
                'trade_in_equity' => $tradeInEquity,
                'approved_discount' => $program->approved_discount,
                'total_down_payment' => $totalDownPayment,
                'principal' => $principal,
                'annual_flat_rate_basis_points' => $rateBasisPoints,
                'total_flat_interest' => $totalFlatInterest,
                'monthly_installment' => $monthlyInstallment,
                'administration_fee' => $administrationFee,
                'provision_fee' => $provisionFee,
                'upfront_insurance' => $upfrontInsurance,
                'other_upfront_cost_label' => $tenor[
                    'other_upfront_cost_label'
                ] ?? null,
                'other_upfront_costs' => $otherUpfrontCosts,
                'initial_payment' => $initialPayment,
                'total_payment' => $totalPayment,
            ],
            'formula_version' => $program->formula_version,
            'valid_until' => $program->effective_to?->toDateString(),
            'warnings' => $warnings,
            'is_estimate' => true,
            'disclaimer' => 'Hasil simulasi bersifat estimasi dan bukan persetujuan kredit. Nilai final mengikuti verifikasi sales dan partner pembiayaan.',
        ];
    }

    /** @param array<string, mixed> $data */
    private function validateProgram(
        CreditProgram $program,
        array $data,
    ): void {
        if (! CreditProgram::query()->effective()->whereKey($program)->exists()) {
            throw ValidationException::withMessages([
                'program_id' => [
                    'Program tidak aktif atau sudah melewati masa berlaku.',
                ],
            ]);
        }
        if ((int) $data['otr_price'] !== $program->otr_price) {
            throw ValidationException::withMessages([
                'otr_price' => [
                    'Harga OTR telah berubah. Muat ulang program kredit.',
                ],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{Appraisal|null, int, list<string>}
     */
    private function tradeIn(User $user, array $data): array
    {
        $appraisalId = $data['trade_in_appraisal_id'] ?? null;
        if (! is_string($appraisalId) || $appraisalId === '') {
            return [
                null,
                (int) ($data['manual_trade_in_value'] ?? 0),
                [],
            ];
        }

        /** @var Appraisal $appraisal */
        $appraisal = $user->appraisals()
            ->with('latestResult')
            ->findOrFail($appraisalId);
        $result = $appraisal->latestResult;
        if ($result === null) {
            throw ValidationException::withMessages([
                'trade_in_appraisal_id' => [
                    'Hasil appraisal belum tersedia.',
                ],
            ]);
        }
        $warnings = [];
        if ($result->valid_until->isPast()) {
            if (! ($data['accept_expired_appraisal'] ?? false)) {
                throw ValidationException::withMessages([
                    'accept_expired_appraisal' => [
                        'Hasil appraisal sudah kedaluwarsa. Konfirmasi untuk tetap menggunakannya sebagai estimasi.',
                    ],
                ]);
            }
            $warnings[] = 'Hasil appraisal sudah kedaluwarsa dan perlu verifikasi ulang.';
        }

        return [
            $appraisal,
            intdiv($result->trade_in_low + $result->trade_in_high, 2),
            $warnings,
        ];
    }

    /** @param array<string, int|string|null> $tenor */
    private function programSnapshot(
        CreditProgram $program,
        array $tenor,
    ): array {
        return [
            'id' => $program->getKey(),
            'program_code' => $program->program_code,
            'version' => $program->version,
            'partner_name' => $program->partner_name,
            'program_name' => $program->program_name,
            'city' => $program->city,
            'vehicle_model' => $program->vehicle_model,
            'vehicle_variant' => $program->vehicle_variant,
            'model_year' => $program->model_year,
            'otr_price' => $program->otr_price,
            'approved_discount' => $program->approved_discount,
            'tenor' => $tenor,
            'formula_strategy' => $program->formula_strategy,
            'formula_version' => $program->formula_version,
            'effective_from' => $program->effective_from->toDateString(),
            'effective_to' => $program->effective_to?->toDateString(),
            'source_reference' => $program->source_reference,
        ];
    }

    private function roundRatio(int $numerator, int $denominator): int
    {
        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }

    private function roundUpRatio(
        int $value,
        int $numerator,
        int $denominator,
    ): int {
        return intdiv(($value * $numerator) + $denominator - 1, $denominator);
    }
}
