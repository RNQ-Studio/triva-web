<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paket servis berkala beserta perkiraan biayanya.
 *
 * Notulensi 19 Agustus 2026: "Apakah bisa ditambahkan simulasi penghitungan
 * biaya service - khusus berkala saja misalnya / just ganti oli", dengan
 * catatan kebutuhan "Data Paket reguler" dari cabang.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('toyota_service_packages')) {
            return;
        }

        Schema::create('toyota_service_packages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->text('description')->nullable();
            // Kosong berarti paket berlaku untuk seluruh model Toyota.
            $table->string('vehicle_model', 100)->nullable();
            $table->unsignedInteger('km_interval');
            $table->unsignedBigInteger('parts_cost')->default(0);
            $table->unsignedBigInteger('labor_cost')->default(0);
            $table->json('includes')->nullable();
            $table->unsignedSmallInteger('duration_min_minutes')->default(60);
            $table->unsignedSmallInteger('duration_max_minutes')->default(180);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('source_reference', 200);
            $table->timestamps();

            $table->unique(
                ['code', 'vehicle_model', 'km_interval'],
                'service_packages_identity_unique',
            );
            $table->index(
                ['is_active', 'vehicle_model', 'km_interval'],
                'service_packages_lookup_idx',
            );
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE toyota_service_packages
                ADD CONSTRAINT service_packages_km_interval_check
                CHECK (km_interval > 0)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('toyota_service_packages');
    }
};
