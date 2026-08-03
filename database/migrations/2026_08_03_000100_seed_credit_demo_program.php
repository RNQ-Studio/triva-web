<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PROGRAM_ID = '00000000-0000-4000-8000-000000000401';

    /** @var array<string, int|string> */
    private const IDENTITY = [
        'program_code' => 'TRIVA-DEMO-CREDIT',
        'version' => 1,
        'city' => 'Surabaya',
        'vehicle_model' => 'Avanza',
        'vehicle_variant' => '1.5 G CVT',
    ];

    public function up(): void
    {
        $existing = DB::table('credit_programs')
            ->where(self::IDENTITY)
            ->first(['id', 'is_demo']);

        if ($existing !== null) {
            if (! (bool) $existing->is_demo) {
                throw new RuntimeException(
                    'Identitas program demo sudah dipakai oleh program non-demo.',
                );
            }

            return;
        }

        if (DB::table('credit_programs')->where('id', self::PROGRAM_ID)->exists()) {
            throw new RuntimeException(
                'UUID program demo sudah dipakai oleh program lain.',
            );
        }

        DB::table('credit_programs')->insert([
            'id' => self::PROGRAM_ID,
            ...self::IDENTITY,
            'partner_name' => 'TRIVA Demo - Bukan Partner Pembiayaan',
            'program_name' => 'Program Demo - Bukan Penawaran Kredit',
            'model_year' => 2026,
            'otr_price' => 320000000,
            'approved_discount' => 10000000,
            'minimum_dp_basis_points' => 2000,
            'maximum_dp_basis_points' => 8000,
            'tenor_options' => json_encode(
                $this->tenorOptions(),
                JSON_THROW_ON_ERROR,
            ),
            'formula_strategy' => 'flat_rate',
            'formula_version' => 'flat-v1',
            'effective_from' => '2026-08-01',
            'effective_to' => '2026-08-31',
            'source_reference' => 'Data demonstrasi internal TRIVA. Seluruh harga, diskon, bunga, dan biaya bersifat dummy; bukan program partner atau penawaran kredit.',
            'is_demo' => true,
            'status' => 'approved',
            'approved_by' => null,
            'approved_at' => '2026-08-02 17:00:00+00',
            'created_at' => '2026-08-02 17:00:00+00',
            'updated_at' => '2026-08-02 17:00:00+00',
        ]);
    }

    public function down(): void
    {
        $program = DB::table('credit_programs')
            ->where(self::IDENTITY)
            ->where('is_demo', true)
            ->first(['id']);

        if ($program === null) {
            return;
        }

        $isReferenced = DB::table('credit_simulations')
            ->where('credit_program_id', $program->id)
            ->exists();

        if ($isReferenced) {
            throw new RuntimeException(
                'Program demo sudah direferensikan snapshot. Rollback dihentikan agar penanda demo tidak hilang.',
            );
        }

        DB::table('credit_programs')
            ->where('id', $program->id)
            ->delete();
    }

    /** @return list<array<string, int|string>> */
    private function tenorOptions(): array
    {
        return [
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
        ];
    }
};
