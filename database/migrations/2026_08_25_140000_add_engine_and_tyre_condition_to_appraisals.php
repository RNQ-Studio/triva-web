<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Permintaan meeting 19 Agustus 2026: persentase kondisi diganti dua
        // pertanyaan konkret -- kondisi mesin (basah/normal) dan ban
        // (rusak/normal). Kolom dibuat nullable supaya appraisal lama tetap sah.
        Schema::table('appraisals', function (Blueprint $table): void {
            if (! Schema::hasColumn('appraisals', 'engine_condition')) {
                $table->string('engine_condition', 20)->nullable();
            }

            if (! Schema::hasColumn('appraisals', 'tyre_condition')) {
                $table->string('tyre_condition', 20)->nullable();
            }
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE appraisals DROP CONSTRAINT IF EXISTS appraisals_engine_condition_check'
            );
            DB::statement(
                "ALTER TABLE appraisals ADD CONSTRAINT appraisals_engine_condition_check
                CHECK (engine_condition IS NULL OR engine_condition IN ('normal', 'wet'))"
            );
            DB::statement(
                'ALTER TABLE appraisals DROP CONSTRAINT IF EXISTS appraisals_tyre_condition_check'
            );
            DB::statement(
                "ALTER TABLE appraisals ADD CONSTRAINT appraisals_tyre_condition_check
                CHECK (tyre_condition IS NULL OR tyre_condition IN ('normal', 'damaged'))"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE appraisals DROP CONSTRAINT IF EXISTS appraisals_engine_condition_check'
            );
            DB::statement(
                'ALTER TABLE appraisals DROP CONSTRAINT IF EXISTS appraisals_tyre_condition_check'
            );
        }

        Schema::table('appraisals', function (Blueprint $table): void {
            $table->dropColumn(['engine_condition', 'tyre_condition']);
        });
    }
};
