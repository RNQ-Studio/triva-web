<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Token publik untuk halaman pembaruan status booking servis Toyota.
 *
 * Revisi 4 September 2026 meminta teks WhatsApp booking menyertakan tautan
 * web unik (tanpa login) agar PIC cabang dapat memperbarui status booking
 * dari menunggu, diproses, hingga selesai. Token UUID acak menjadi satu-
 * satunya kunci akses halaman itu, jadi harus unik dan tidak bisa ditebak.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('toyota_service_bookings', 'public_token')) {
            Schema::table('toyota_service_bookings', function (Blueprint $table): void {
                $table->uuid('public_token')->nullable()->unique('tsb_public_token_unique');
            });
        }

        DB::table('toyota_service_bookings')
            ->whereNull('public_token')
            ->orderBy('id')
            ->select('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('toyota_service_bookings')
                        ->where('id', $row->id)
                        ->update(['public_token' => (string) Str::uuid()]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('toyota_service_bookings', 'public_token')) {
            Schema::table('toyota_service_bookings', function (Blueprint $table): void {
                $table->dropUnique('tsb_public_token_unique');
                $table->dropColumn('public_token');
            });
        }
    }
};
