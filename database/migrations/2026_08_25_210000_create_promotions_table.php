<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Konten promo yang tampil sebagai banner berjalan dan pop-up di halaman depan.
 *
 * Notulensi 19 Agustus 2026 meminta "Pop Up Promo konten berjalan Sales,
 * Service GR, Service BP, OtoXpert (Update per Month)". Periode tayang dibuat
 * eksplisit supaya cabang bisa menyiapkan promo bulan berikutnya lebih awal
 * tanpa mengganggu yang sedang berjalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('promotions')) {
            return;
        }

        Schema::create('promotions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('category', 20);
            $table->string('title', 150);
            $table->string('subtitle', 200)->nullable();
            $table->text('description')->nullable();
            $table->string('image_path', 255)->nullable();
            $table->string('cta_label', 60)->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            // Pop-up hanya untuk promo unggulan, supaya pelanggan tidak
            // disambut tumpukan dialog setiap membuka aplikasi.
            $table->boolean('show_as_popup')->default(false);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestamps();

            $table->index(
                ['is_active', 'starts_on', 'ends_on'],
                'promotions_window_idx',
            );
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE promotions ADD CONSTRAINT promotions_category_check
                CHECK (category IN ('sales', 'service_gr', 'service_bp', 'otoxpert'))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
