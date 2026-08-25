<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // OLX mengklasifikasi unit ke empat tier (A-D) dan itulah yang dipakai
        // saat menawar, sehingga cabang meminta pelanggan memilih grade yang
        // sama alih-alih menaksir persentase.
        Schema::table('appraisals', function (Blueprint $table): void {
            if (! Schema::hasColumn('appraisals', 'condition_grade')) {
                $table->char('condition_grade', 1)->nullable();
            }
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE appraisals DROP CONSTRAINT IF EXISTS appraisals_condition_grade_check'
            );
            DB::statement(
                "ALTER TABLE appraisals ADD CONSTRAINT appraisals_condition_grade_check
                CHECK (condition_grade IS NULL OR condition_grade IN ('a', 'b', 'c', 'd'))"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE appraisals DROP CONSTRAINT IF EXISTS appraisals_condition_grade_check'
            );
        }

        Schema::table('appraisals', function (Blueprint $table): void {
            $table->dropColumn('condition_grade');
        });
    }
};
