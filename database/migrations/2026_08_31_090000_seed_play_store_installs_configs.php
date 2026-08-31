<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Kunci App Config untuk total download Play Store.
 *
 * Tidak ada API Google Play yang mengembalikan jumlah instal, jadi angkanya
 * disalin admin dari Play Console sampai ekspor laporan Play Console ke bucket
 * Cloud Storage diaktifkan.
 *
 * Tipenya `string`, bukan `integer`, supaya nilai kosong tetap bisa dibedakan
 * dari nol pemasangan: `AppConfig` mengecor integer kosong menjadi 0.
 */
return new class extends Migration
{
    /** @var array<int, array<string, string>> */
    private const CONFIGS = [
        [
            'key' => 'play_store_total_installs',
            'value' => '',
            'type' => 'string',
            'description' => 'Total download (Total user installs) di Google Play. Salin dari Play Console > Statistik. Kosongkan kalau belum ada angka.',
        ],
        [
            'key' => 'play_store_installs_reported_at',
            'value' => '',
            'type' => 'string',
            'description' => 'Tanggal angka total download di atas berlaku, format YYYY-MM-DD. Ditampilkan sebagai keterangan "data per" di panel admin.',
        ],
    ];

    public function up(): void
    {
        foreach (self::CONFIGS as $config) {
            // Angka yang sudah diisi admin lewat panel tidak ditimpa.
            if (DB::table('app_configs')->where('key', $config['key'])->exists()) {
                continue;
            }

            DB::table('app_configs')->insert($config + [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Cache::forget('app_config:all');
    }

    public function down(): void
    {
        DB::table('app_configs')
            ->whereIn('key', array_column(self::CONFIGS, 'key'))
            ->delete();

        Cache::forget('app_config:all');
    }
};
