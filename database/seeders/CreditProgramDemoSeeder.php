<?php

namespace Database\Seeders;

use App\Models\CreditProgram;
use App\Support\Enums\CreditProgramStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

class CreditProgramDemoSeeder extends Seeder
{
    public function run(): void
    {
        $identity = [
            'program_code' => 'TRIVA-DEMO-CREDIT',
            'version' => 1,
            'city' => 'Surabaya',
            'vehicle_model' => 'Avanza',
            'vehicle_variant' => '1.5 G CVT',
        ];

        $program = CreditProgram::query()->firstOrCreate($identity, [
            'partner_name' => 'TRIVA Demo - Bukan Partner Pembiayaan',
            'program_name' => 'Program Demo - Bukan Penawaran Kredit',
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
                    'other_upfront_cost_label' => 'Biaya fidusia demo',
                    'other_upfront_costs' => 500000,
                ],
                [
                    'tenor_months' => 48,
                    'annual_flat_rate_basis_points' => 490,
                    'administration_fee' => 2500000,
                    'provision_fee' => 1000000,
                    'upfront_insurance' => 6750000,
                    'other_upfront_cost_label' => 'Biaya fidusia demo',
                    'other_upfront_costs' => 500000,
                ],
                [
                    'tenor_months' => 60,
                    'annual_flat_rate_basis_points' => 525,
                    'administration_fee' => 2500000,
                    'provision_fee' => 1000000,
                    'upfront_insurance' => 7500000,
                    'other_upfront_cost_label' => 'Biaya fidusia demo',
                    'other_upfront_costs' => 500000,
                ],
            ],
            'formula_strategy' => 'flat_rate',
            'formula_version' => 'flat-v1',
            'effective_from' => '2026-08-01',
            'effective_to' => '2026-08-31',
            'source_reference' => 'Data demonstrasi internal TRIVA. Seluruh harga, diskon, bunga, dan biaya bersifat dummy; bukan program partner atau penawaran kredit.',
            'is_demo' => true,
            'status' => CreditProgramStatus::Approved,
            'approved_by' => null,
            'approved_at' => Carbon::parse(
                '2026-08-03 00:00:00',
                'Asia/Jakarta',
            )->utc(),
        ]);

        if (! $program->is_demo) {
            throw new RuntimeException(
                'Identitas program demo sudah dipakai oleh program non-demo.',
            );
        }
    }
}
