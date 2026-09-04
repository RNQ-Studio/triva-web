<?php

namespace App\Services;

use App\Models\CreditProgram;
use App\Models\CreditSimulation;
use App\Models\User;
use App\Services\Credit\AccCreditCalculator;
use App\Support\Enums\CreditSimulationStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * Simulasi kredit cepat (revisi 4 September 2026): pelanggan memilih unit,
 * mengisi OTR, memilih DP 20/25/30% dan tenor 1-5 tahun. Hasilnya disimpan
 * sebagai CreditSimulation supaya tampil di panel admin, lalu admin diberi
 * notifikasi in-app.
 */
class CreditQuickSimulationService
{
    public function __construct(
        private readonly AccCreditCalculator $calculator,
        private readonly AdminNotificationService $adminNotifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, CreditProgram $program, array $data): CreditSimulation
    {
        $otrPrice = (int) $data['otr_price'];
        $quote = $this->calculator->quote(
            $otrPrice,
            (int) $data['dp_percent'],
            (int) $data['tenor_years'],
            $program->vehicle_model,
        );

        $inputs = [
            'otr_price' => $otrPrice,
            'dp_percent' => (int) $data['dp_percent'],
            'tenor_years' => (int) $data['tenor_years'],
            'tenor_months' => $quote['tenor_months'],
            'cash_down_payment' => $quote['down_payment'],
            'trade_in_appraisal_id' => null,
            'trade_in_value' => 0,
            'use_trade_in_as_dp' => false,
            'old_vehicle_payoff' => 0,
            'mode' => 'quick_acc',
        ];
        $calculation = [
            ...$quote,
            // Kunci standar yang dibaca aplikasi lama dan panel admin.
            'trade_in_equity' => 0,
            'approved_discount' => 0,
            'total_down_payment' => $quote['down_payment'],
            'provision_fee' => 0,
            'upfront_insurance' => $quote['liability_insurance_fee'],
            'other_upfront_cost_label' => 'Angsuran pertama',
            'other_upfront_costs' => $quote['first_installment'],
            'initial_payment' => $quote['total_down_payment'],
        ];

        $simulation = DB::transaction(function () use ($user, $program, $data, $inputs, $calculation, $quote): CreditSimulation {
            $simulation = CreditSimulation::query()->create([
                'reference_no' => $this->referenceNumber(),
                'user_id' => $user->getKey(),
                'credit_program_id' => $program->getKey(),
                'appraisal_id' => null,
                'comparison_group_id' => null,
                'status' => CreditSimulationStatus::Saved,
                'program_snapshot' => $this->programSnapshot($program, $otrPrice = $inputs['otr_price']),
                'input_snapshot' => $inputs,
                'calculation_snapshot' => $calculation,
                'formula_version' => $quote['formula_version'],
                'otr_price' => $otrPrice,
                'cash_down_payment' => $quote['down_payment'],
                'trade_in_value' => 0,
                'old_vehicle_payoff' => 0,
                'trade_in_equity' => 0,
                'use_trade_in_as_dp' => false,
                'approved_discount' => 0,
                'total_down_payment' => $quote['down_payment'],
                'principal' => $quote['principal'],
                'tenor_months' => $quote['tenor_months'],
                'annual_flat_rate_basis_points' => $quote['annual_flat_rate_basis_points'],
                'total_flat_interest' => $quote['total_flat_interest'],
                'monthly_installment' => $quote['monthly_installment'],
                'administration_fee' => $quote['administration_fee'],
                'provision_fee' => 0,
                'upfront_insurance' => $quote['liability_insurance_fee'],
                'other_upfront_costs' => $quote['first_installment'],
                'initial_payment' => $quote['total_down_payment'],
                'total_payment' => $quote['total_payment'],
                'valid_until' => $program->effective_to,
                'campaign_source' => $data['campaign_source'] ?? 'triva_quick_credit',
                'idempotency_key' => (string) Str::uuid(),
                'request_fingerprint' => hash('sha256', json_encode($inputs, JSON_THROW_ON_ERROR)),
                'saved_at' => now(),
            ]);

            $this->adminNotifications->notify(
                'credit_simulation',
                'Simulasi kredit baru',
                sprintf(
                    '%s menghitung %s %s: OTR Rp %s, DP %d%%, tenor %d tahun, angsuran Rp %s/bulan.',
                    $user->name,
                    $program->vehicle_model,
                    $program->vehicle_variant,
                    Number::format($otrPrice, locale: 'id'),
                    $inputs['dp_percent'],
                    $inputs['tenor_years'],
                    Number::format($quote['monthly_installment'], locale: 'id'),
                ),
                [
                    'credit_simulation_id' => $simulation->getKey(),
                    'reference_no' => $simulation->reference_no,
                    'customer_user_id' => $user->getKey(),
                    'route' => '/admin/credit/simulations/'.$simulation->getKey(),
                ],
            );

            return $simulation;
        }, 3);

        return $simulation->load(['program', 'followUpLead.assignedSales']);
    }

    /** @return array<string, mixed> */
    private function programSnapshot(CreditProgram $program, int $otrPrice): array
    {
        return [
            'id' => $program->getKey(),
            'program_code' => $program->program_code,
            'version' => $program->version,
            'partner_name' => 'ACC',
            'program_name' => 'Simulasi cepat ACC',
            'city' => $program->city,
            'vehicle_model' => $program->vehicle_model,
            'vehicle_variant' => $program->vehicle_variant,
            'model_year' => $program->model_year,
            'unit_key' => $program->unit_key,
            'image_url' => $program->imageUrl(),
            'otr_price' => $otrPrice,
            'catalog_otr_price' => $program->otr_price,
            'approved_discount' => 0,
            'formula_strategy' => 'acc_flat',
            'formula_version' => (string) config('credit_acc.formula_version'),
            'effective_from' => $program->effective_from->toDateString(),
            'effective_to' => $program->effective_to?->toDateString(),
            'source_reference' => 'Rate card Simulasi_ACC (revisi 4 September 2026).',
            'is_demo' => $program->is_demo,
        ];
    }

    private function referenceNumber(): string
    {
        do {
            $reference = 'SK-'.now()->format('ymd').'-'.strtoupper(Str::random(8));
        } while (CreditSimulation::query()->where('reference_no', $reference)->exists());

        return $reference;
    }
}
