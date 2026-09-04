<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar sales dan supervisor cabang yang bisa dihubungi pelanggan lewat
 * WhatsApp dari aplikasi (revisi 4 September 2026).
 *
 * Dipisahkan dari tabel users karena sales tidak login ke TRIVA; yang
 * dibutuhkan pelanggan hanya nama, foto, nomor WhatsApp, dan perannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_contacts')) {
            return;
        }

        Schema::create('sales_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('role', 10);
            $table->string('whatsapp_number', 20);
            $table->string('photo_path', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'role', 'sort_order'], 'sales_contacts_listing_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE sales_contacts ADD CONSTRAINT sales_contacts_role_check
                CHECK (role IN ('sales', 'spv'))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_contacts');
    }
};
