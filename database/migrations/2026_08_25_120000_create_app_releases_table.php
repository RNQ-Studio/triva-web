<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog build APK yang dihost sendiri untuk distribusi in-app.
 *
 * Satu baris = satu build yang diunggah. Baris `is_active` adalah rilis yang
 * ditawarkan ke aplikasi; `apk_sha256` dipakai klien untuk memverifikasi
 * integritas unduhan sebelum memicu installer sistem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_releases', function (Blueprint $table): void {
            $table->id();
            $table->enum('platform', ['android', 'ios'])->default('android');
            $table->unsignedInteger('version_code');
            $table->string('version_name', 50);
            // URL absolut biner di object storage (GCS), bukan disk aplikasi,
            // supaya artifact selamat dari deploy dan rebuild server.
            $table->text('apk_url');
            $table->string('apk_sha256', 64);
            $table->unsignedBigInteger('apk_size_bytes');
            // Path object di bucket; disimpan agar biner bisa dihapus tanpa
            // mem-parse ulang apk_url.
            $table->string('storage_path');
            $table->boolean('is_active')->default(false);
            $table->text('release_notes')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'version_code']);
            $table->index(['platform', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_releases');
    }
};
