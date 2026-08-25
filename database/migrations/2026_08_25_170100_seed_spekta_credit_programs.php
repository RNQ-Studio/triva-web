<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Paket SPEKTA DP 20% yang diminta pada meeting 19 Agustus 2026.
 *
 * Ditulis sebagai migrasi -- bukan seeder -- karena produksi hanya menjalankan
 * migrate. Rate card resmi SPEKTA belum diserahkan cabang, jadi bunga dan biaya
 * di sini masih placeholder dan programnya ditandai demo. Yang sudah final
 * adalah bentuk paketnya: DP anjuran 20% dan tenor yang tersedia. Saat
 * pricelist resmi masuk, program diganti versi baru lewat import CSV.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->programs() as $index => $definition) {
            $identity = [
                'program_code' => $definition['program_code'],
                'version' => 1,
                'city' => 'Surabaya',
                'vehicle_model' => $definition['vehicle_model'],
                'vehicle_variant' => $definition['vehicle_variant'],
            ];

            $existing = DB::table('credit_programs')
                ->where($identity)
                ->first(['id', 'package_code']);

            if ($existing !== null) {
                if ($existing->package_code !== 'spekta') {
                    throw new RuntimeException(
                        'Identitas program SPEKTA sudah dipakai program lain: '
                        .$definition['program_code'],
                    );
                }

                continue;
            }

            $id = sprintf('00000000-0000-4000-8000-0000000005%02d', $index + 1);
            if (DB::table('credit_programs')->where('id', $id)->exists()) {
                throw new RuntimeException(
                    'UUID program SPEKTA sudah dipakai program lain: '.$id,
                );
            }

            DB::table('credit_programs')->insert([
                'id' => $id,
                ...$identity,
                'partner_name' => $definition['partner_name'],
                'program_name' => 'SPEKTA DP 20% - Menunggu Rate Card Resmi',
                'package_code' => 'spekta',
                'model_year' => 2026,
                'otr_price' => $definition['otr_price'],
                'approved_discount' => 0,
                'recommended_dp_basis_points' => 2000,
                'minimum_dp_basis_points' => 2000,
                'maximum_dp_basis_points' => 8000,
                'tenor_options' => json_encode(
                    $this->tenorOptions(),
                    JSON_THROW_ON_ERROR,
                ),
                'formula_strategy' => 'flat_rate',
                'formula_version' => 'flat-v1',
                'effective_from' => '2026-08-01',
                'effective_to' => null,
                'source_reference' => 'Kerangka paket SPEKTA hasil meeting 19 Agustus 2026. Bunga dan biaya masih placeholder sampai rate card resmi diterima; bukan penawaran kredit.',
                'is_demo' => true,
                'status' => 'approved',
                'approved_by' => null,
                'approved_at' => '2026-08-24 17:00:00+00',
                'created_at' => '2026-08-24 17:00:00+00',
                'updated_at' => '2026-08-24 17:00:00+00',
            ]);
        }
    }

    public function down(): void
    {
        $programs = DB::table('credit_programs')
            ->where('package_code', 'spekta')
            ->pluck('id');

        $isReferenced = DB::table('credit_simulations')
            ->whereIn('credit_program_id', $programs)
            ->exists();

        if ($isReferenced) {
            throw new RuntimeException(
                'Program SPEKTA sudah direferensikan snapshot simulasi. Rollback dihentikan.',
            );
        }

        DB::table('credit_programs')->whereIn('id', $programs)->delete();
    }

    /** @return list<array<string, int|string>> */
    private function programs(): array
    {
        return [
            [
                'program_code' => 'SPEKTA-AVANZA-2026',
                'partner_name' => 'ACC',
                'vehicle_model' => 'Avanza',
                'vehicle_variant' => '1.5 G CVT',
                'otr_price' => 320000000,
            ],
            [
                'program_code' => 'SPEKTA-VELOZ-2026',
                'partner_name' => 'ACC',
                'vehicle_model' => 'Veloz',
                'vehicle_variant' => '1.5 Q CVT TSS',
                'otr_price' => 360000000,
            ],
            [
                'program_code' => 'SPEKTA-RUSH-2026',
                'partner_name' => 'TAF',
                'vehicle_model' => 'Rush',
                'vehicle_variant' => '1.5 S GR Sport',
                'otr_price' => 305000000,
            ],
            [
                'program_code' => 'SPEKTA-INNOVA-2026',
                'partner_name' => 'TAF',
                'vehicle_model' => 'Kijang Innova Zenix',
                'vehicle_variant' => '2.0 V CVT',
                'otr_price' => 470000000,
            ],
        ];
    }

    /** @return list<array<string, int|string>> */
    private function tenorOptions(): array
    {
        $rates = [
            12 => [380, 5000000],
            24 => [420, 5500000],
            36 => [450, 6000000],
            48 => [490, 6750000],
            60 => [525, 7500000],
        ];

        $options = [];
        foreach ($rates as $months => [$rate, $insurance]) {
            $options[] = [
                'tenor_months' => $months,
                'annual_flat_rate_basis_points' => $rate,
                'administration_fee' => 2500000,
                'provision_fee' => 1000000,
                'upfront_insurance' => $insurance,
                'other_upfront_cost_label' => 'Biaya fidusia',
                'other_upfront_costs' => 500000,
            ];
        }

        return $options;
    }
};
