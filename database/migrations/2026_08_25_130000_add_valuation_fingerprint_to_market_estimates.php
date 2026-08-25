<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('appraisal_market_estimates', 'valuation_fingerprint')) {
            return;
        }

        Schema::table('appraisal_market_estimates', function (Blueprint $table): void {
            // Sidik jari kendaraan + kondisi, bukan pemilik akun. Dua pelanggan
            // yang mengisi data identik harus menerima angka identik selama
            // data pasarnya masih berlaku.
            $table->char('valuation_fingerprint', 64)->nullable();
            $table->index(
                ['valuation_fingerprint', 'status', 'calculated_at'],
                'appraisal_market_estimates_fingerprint_index',
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('appraisal_market_estimates', 'valuation_fingerprint')) {
            return;
        }

        Schema::table('appraisal_market_estimates', function (Blueprint $table): void {
            $table->dropIndex('appraisal_market_estimates_fingerprint_index');
            $table->dropColumn('valuation_fingerprint');
        });
    }
};
