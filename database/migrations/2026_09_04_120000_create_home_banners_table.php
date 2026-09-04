<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banner iklan bergambar yang berputar (slider) di beranda aplikasi.
 *
 * Berbeda dari promo yang berupa kartu teks per kategori, banner ini murni
 * gambar landscape yang diunggah cabang lewat panel admin (revisi 4 September
 * 2026), dengan periode tayang opsional dan tautan opsional saat diketuk.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('home_banners')) {
            return;
        }

        Schema::create('home_banners', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title', 150);
            $table->string('image_path', 255);
            $table->string('link_url', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'starts_on', 'ends_on'], 'home_banners_window_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_banners');
    }
};
