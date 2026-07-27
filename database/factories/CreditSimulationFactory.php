<?php

namespace Database\Factories;

use App\Models\CreditProgram;
use App\Models\CreditSimulation;
use App\Models\User;
use App\Support\Enums\CreditSimulationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CreditSimulation> */
class CreditSimulationFactory extends Factory
{
    protected $model = CreditSimulation::class;

    public function definition(): array
    {
        return [
            'reference_no' => 'SK-'.now('Asia/Jakarta')->format('ymd').'-'
                .strtoupper(Str::random(8)),
            'user_id' => User::factory(),
            'credit_program_id' => CreditProgram::factory(),
            'status' => CreditSimulationStatus::Saved,
            'program_snapshot' => [
                'program_name' => 'Program Flat Test',
            ],
            'input_snapshot' => [],
            'calculation_snapshot' => [],
            'formula_version' => 'flat-v1',
            'otr_price' => 320000000,
            'cash_down_payment' => 70000000,
            'trade_in_value' => 0,
            'old_vehicle_payoff' => 0,
            'trade_in_equity' => 0,
            'use_trade_in_as_dp' => false,
            'approved_discount' => 10000000,
            'total_down_payment' => 80000000,
            'principal' => 240000000,
            'tenor_months' => 60,
            'annual_flat_rate_basis_points' => 525,
            'total_flat_interest' => 63000000,
            'monthly_installment' => 5050000,
            'administration_fee' => 2500000,
            'provision_fee' => 1000000,
            'upfront_insurance' => 7500000,
            'other_upfront_costs' => 500000,
            'initial_payment' => 81500000,
            'total_payment' => 384500000,
            'valid_until' => now('Asia/Jakarta')->addMonth()->toDateString(),
            'idempotency_key' => (string) Str::uuid(),
            'request_fingerprint' => hash('sha256', Str::random(32)),
            'saved_at' => now(),
        ];
    }
}
