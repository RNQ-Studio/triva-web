<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notulensi 19 Agustus 2026: estimasi Body & Paint menanyakan apakah
        // kendaraan diasuransikan, dan bila ya nominal estimasinya tidak perlu
        // ditampilkan karena biayanya ditanggung klaim.
        Schema::table('body_paint_estimates', function (Blueprint $table): void {
            if (! Schema::hasColumn('body_paint_estimates', 'is_insured')) {
                $table->boolean('is_insured')->default(false);
            }

            if (! Schema::hasColumn('body_paint_estimates', 'insurance_provider')) {
                $table->string('insurance_provider', 120)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('body_paint_estimates', function (Blueprint $table): void {
            $table->dropColumn(['is_insured', 'insurance_provider']);
        });
    }
};
