<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notulensi 19 & 20 Agustus 2026: saat pelanggan menekan "belum cocok",
        // dia langsung mengisi harga harapannya. Ini mengubah penolakan menjadi
        // lead yang bisa ditindaklanjuti sales, bukan sekadar data hilang.
        Schema::table('appraisals', function (Blueprint $table): void {
            if (! Schema::hasColumn('appraisals', 'expected_price')) {
                $table->unsignedBigInteger('expected_price')->nullable();
            }

            if (! Schema::hasColumn('appraisals', 'expected_price_submitted_at')) {
                $table->timestamp('expected_price_submitted_at')->nullable();
            }
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE appraisals DROP CONSTRAINT IF EXISTS appraisals_expected_price_check'
            );
            DB::statement(
                'ALTER TABLE appraisals ADD CONSTRAINT appraisals_expected_price_check
                CHECK (expected_price IS NULL OR expected_price > 0)'
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE appraisals DROP CONSTRAINT IF EXISTS appraisals_expected_price_check'
            );
        }

        Schema::table('appraisals', function (Blueprint $table): void {
            $table->dropColumn(['expected_price', 'expected_price_submitted_at']);
        });
    }
};
