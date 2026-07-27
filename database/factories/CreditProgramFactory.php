<?php

namespace Database\Factories;

use App\Models\CreditProgram;
use App\Support\Enums\CreditProgramStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CreditProgram> */
class CreditProgramFactory extends Factory
{
    protected $model = CreditProgram::class;

    public function definition(): array
    {
        return [
            'program_code' => 'TRIVA-'.strtoupper(Str::random(8)),
            'version' => 1,
            'partner_name' => 'Partner Pembiayaan Test',
            'program_name' => 'Program Flat Test',
            'city' => 'Surabaya',
            'vehicle_model' => 'Avanza',
            'vehicle_variant' => '1.5 G CVT',
            'model_year' => 2026,
            'otr_price' => 320000000,
            'approved_discount' => 10000000,
            'minimum_dp_basis_points' => 2000,
            'maximum_dp_basis_points' => 8000,
            'tenor_options' => [
                [
                    'tenor_months' => 36,
                    'annual_flat_rate_basis_points' => 450,
                    'administration_fee' => 2500000,
                    'provision_fee' => 1000000,
                    'upfront_insurance' => 6000000,
                    'other_upfront_cost_label' => 'Biaya fidusia',
                    'other_upfront_costs' => 500000,
                ],
                [
                    'tenor_months' => 60,
                    'annual_flat_rate_basis_points' => 525,
                    'administration_fee' => 2500000,
                    'provision_fee' => 1000000,
                    'upfront_insurance' => 7500000,
                    'other_upfront_cost_label' => 'Biaya fidusia',
                    'other_upfront_costs' => 500000,
                ],
            ],
            'formula_strategy' => 'flat_rate',
            'formula_version' => 'flat-v1',
            'effective_from' => now('Asia/Jakarta')->subDay()->toDateString(),
            'effective_to' => now('Asia/Jakarta')->addMonth()->toDateString(),
            'source_reference' => 'Dokumen program pembiayaan test.',
            'status' => CreditProgramStatus::Approved,
            'approved_at' => now(),
        ];
    }
}
