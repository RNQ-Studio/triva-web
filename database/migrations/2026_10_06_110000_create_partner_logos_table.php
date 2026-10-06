<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logo "Mitra resmi" di bagian bawah beranda aplikasi.
 *
 * Sebelumnya lima logo ditanam di aplikasi. Revisi 6 Oktober 2026 meminta
 * admin bisa mengatur logo yang tampil (unggah gambar) beserta tautan yang
 * dibuka di browser saat logo diketuk.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partner_logos')) {
            return;
        }

        Schema::create('partner_logos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('logo_path', 255);
            $table->string('link_url', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'partner_logos_listing_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_logos');
    }
};
