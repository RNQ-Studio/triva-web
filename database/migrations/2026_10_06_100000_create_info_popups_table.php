<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Popup informasi bergambar yang muncul saat beranda aplikasi dibuka.
 *
 * Revisi 6 Oktober 2026: admin mengatur gambar (bisa digeser bila lebih dari
 * satu), tombol opsional berlabel bebas yang membuka tautan, urutan tampil,
 * dan jeda tayang ulang dalam jam. Jeda dihitung aplikasi per perangkat,
 * jadi server cukup menyimpan angkanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('info_popups')) {
            return;
        }

        Schema::create('info_popups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title', 150);
            $table->string('image_path', 255);
            $table->string('button_label', 40)->nullable();
            $table->string('button_url', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('interval_hours')->default(24);
            $table->boolean('is_active')->default(true);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'starts_on', 'ends_on'], 'info_popups_window_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE info_popups
                ADD CONSTRAINT info_popups_interval_hours_check
                CHECK (interval_hours >= 1)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('info_popups');
    }
};
