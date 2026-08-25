<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notulensi 19 Agustus 2026 meminta simulasi kredit dilengkapi paket
        // SPEKTA dengan DP 20%. Paket dan DP anjurannya jadi atribut program
        // supaya aplikasi memilihkan angka yang benar tanpa hardcode.
        Schema::table('credit_programs', function (Blueprint $table): void {
            if (! Schema::hasColumn('credit_programs', 'package_code')) {
                $table->string('package_code', 30)->nullable();
            }

            if (! Schema::hasColumn('credit_programs', 'recommended_dp_basis_points')) {
                $table->unsignedSmallInteger('recommended_dp_basis_points')->nullable();
            }
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE credit_programs
                DROP CONSTRAINT IF EXISTS credit_programs_recommended_dp_check'
            );
            DB::statement(
                'ALTER TABLE credit_programs ADD CONSTRAINT credit_programs_recommended_dp_check
                CHECK (
                    recommended_dp_basis_points IS NULL
                    OR (
                        recommended_dp_basis_points >= minimum_dp_basis_points
                        AND recommended_dp_basis_points <= maximum_dp_basis_points
                    )
                )'
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE credit_programs
                DROP CONSTRAINT IF EXISTS credit_programs_recommended_dp_check'
            );
        }

        Schema::table('credit_programs', function (Blueprint $table): void {
            $table->dropColumn(['package_code', 'recommended_dp_basis_points']);
        });
    }
};
