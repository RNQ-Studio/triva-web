<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gambar unit dan kunci unit pada program kredit (revisi 4 September 2026).
 *
 * Halaman hasil appraisal menawarkan dua unit rekomendasi berdasarkan rentang
 * harga appraisal (Veloz Hybrid, Zenix Hybrid, Reborn, Raize) lengkap dengan
 * gambar, DP, dan angsuran. `unit_key` menjadi rujukan aturan rekomendasi di
 * config/credit_acc.php, sedangkan gambar diunggah cabang lewat panel admin.
 *
 * Empat program unit rekomendasi diseed sebagai data demo: harga OTR-nya
 * placeholder sampai cabang mengisi pricelist resmi lewat panel admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_programs', function (Blueprint $table): void {
            if (! Schema::hasColumn('credit_programs', 'image_path')) {
                $table->string('image_path', 255)->nullable();
            }
            if (! Schema::hasColumn('credit_programs', 'unit_key')) {
                $table->string('unit_key', 40)->nullable()->index();
            }
        });

        foreach ($this->units() as $index => $unit) {
            $identity = [
                'program_code' => $unit['program_code'],
                'version' => 1,
                'city' => 'Surabaya',
                'vehicle_model' => $unit['vehicle_model'],
                'vehicle_variant' => $unit['vehicle_variant'],
            ];
            $existing = DB::table('credit_programs')->where($identity)->first(['id', 'unit_key']);
            if ($existing !== null) {
                if ($existing->unit_key === null) {
                    DB::table('credit_programs')
                        ->where('id', $existing->id)
                        ->update(['unit_key' => $unit['unit_key']]);
                }

                continue;
            }

            $id = sprintf('00000000-0000-4000-8000-0000000006%02d', $index + 1);
            if (DB::table('credit_programs')->where('id', $id)->exists()) {
                continue;
            }

            DB::table('credit_programs')->insert([
                'id' => $id,
                ...$identity,
                'partner_name' => 'ACC',
                'program_name' => 'Unit rekomendasi - '.$unit['vehicle_model'],
                'package_code' => null,
                'unit_key' => $unit['unit_key'],
                'image_path' => null,
                'model_year' => 2026,
                'otr_price' => $unit['otr_price'],
                'approved_discount' => 0,
                'recommended_dp_basis_points' => 2000,
                'minimum_dp_basis_points' => 1000,
                'maximum_dp_basis_points' => 9000,
                'tenor_options' => json_encode($this->tenorOptions(), JSON_THROW_ON_ERROR),
                'formula_strategy' => 'flat_rate',
                'formula_version' => 'flat-v1',
                'effective_from' => '2026-09-01',
                'effective_to' => null,
                'source_reference' => 'Unit rekomendasi hasil appraisal (revisi 4 September 2026). Harga OTR placeholder sampai pricelist resmi cabang diisi lewat panel admin.',
                'is_demo' => true,
                'status' => 'approved',
                'approved_by' => null,
                'approved_at' => '2026-09-04 03:00:00+00',
                'created_at' => '2026-09-04 03:00:00+00',
                'updated_at' => '2026-09-04 03:00:00+00',
            ]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('credit_programs')
            ->whereIn('unit_key', array_column($this->units(), 'unit_key'))
            ->where('is_demo', true)
            ->pluck('id');
        $referenced = DB::table('credit_simulations')->whereIn('credit_program_id', $ids)->exists();
        if (! $referenced) {
            DB::table('credit_programs')->whereIn('id', $ids)->delete();
        }

        Schema::table('credit_programs', function (Blueprint $table): void {
            if (Schema::hasColumn('credit_programs', 'image_path')) {
                $table->dropColumn('image_path');
            }
            if (Schema::hasColumn('credit_programs', 'unit_key')) {
                $table->dropColumn('unit_key');
            }
        });
    }

    /** @return list<array<string, int|string>> */
    private function units(): array
    {
        return [
            [
                'program_code' => 'UNIT-VELOZ-HYBRID-2026',
                'unit_key' => 'veloz_hybrid',
                'vehicle_model' => 'Veloz Hybrid',
                'vehicle_variant' => '1.5 Q HEV CVT TSS',
                'otr_price' => 405_000_000,
            ],
            [
                'program_code' => 'UNIT-ZENIX-HYBRID-2026',
                'unit_key' => 'zenix_hybrid',
                'vehicle_model' => 'Kijang Innova Zenix Hybrid',
                'vehicle_variant' => '2.0 G HV CVT',
                'otr_price' => 490_000_000,
            ],
            [
                'program_code' => 'UNIT-INNOVA-REBORN-2026',
                'unit_key' => 'innova_reborn',
                'vehicle_model' => 'Kijang Innova Reborn',
                'vehicle_variant' => '2.4 G AT Diesel',
                'otr_price' => 425_000_000,
            ],
            [
                'program_code' => 'UNIT-RAIZE-2026',
                'unit_key' => 'raize',
                'vehicle_model' => 'Raize',
                'vehicle_variant' => '1.0T G CVT',
                'otr_price' => 275_000_000,
            ],
        ];
    }

    /** @return list<array<string, int|string>> */
    private function tenorOptions(): array
    {
        $options = [];
        foreach ([12 => 620, 24 => 720, 36 => 770, 48 => 920, 60 => 970] as $months => $rate) {
            $options[] = [
                'tenor_months' => $months,
                'annual_flat_rate_basis_points' => $rate,
                'administration_fee' => 1000000,
                'provision_fee' => 0,
                'upfront_insurance' => 400000,
                'other_upfront_cost_label' => null,
                'other_upfront_costs' => 0,
            ];
        }

        return $options;
    }
};
