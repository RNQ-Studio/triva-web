<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Nomor WhatsApp tujuan booking hasil meeting 19 Agustus 2026.
 *
 * Notulensi mencantumkan "Booking Service Toyota & SSC : 0857-1311-2000" dan
 * "Booking OtoXpert : 0815-1106-0290", dan meminta pelanggan tersambung
 * langsung ke nomor itu setelah mengisi form. Estimasi Body & Paint memakai
 * nomor cabang yang sama. Disimpan sebagai konfigurasi supaya cabang bisa
 * mengganti nomor tanpa merilis ulang aplikasi.
 */
return new class extends Migration
{
    /** @var array<int, array<string, string>> */
    private const CONFIGS = [
        [
            'key' => 'whatsapp_toyota_service',
            'value' => '6285713112000',
            'type' => 'string',
            'description' => 'Nomor WhatsApp booking servis Toyota dan cek SSC Auto2000 Kertajaya',
        ],
        [
            'key' => 'whatsapp_otoxpert',
            'value' => '6281511060290',
            'type' => 'string',
            'description' => 'Nomor WhatsApp booking OtoXpert',
        ],
        [
            'key' => 'whatsapp_body_paint',
            'value' => '6285713112000',
            'type' => 'string',
            'description' => 'Nomor WhatsApp PIC Body & Paint Auto2000 Kertajaya',
        ],
    ];

    public function up(): void
    {
        foreach (self::CONFIGS as $config) {
            // Nomor yang sudah diubah cabang lewat panel admin tidak ditimpa.
            if (DB::table('app_configs')->where('key', $config['key'])->exists()) {
                continue;
            }

            DB::table('app_configs')->insert([
                ...$config,
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
