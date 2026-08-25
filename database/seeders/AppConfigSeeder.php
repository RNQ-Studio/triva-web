<?php

namespace Database\Seeders;

use App\Models\AppConfig;
use App\Models\AppVersion;
use Illuminate\Database\Seeder;

class AppConfigSeeder extends Seeder
{
    public function run(): void
    {
        // Default app versions
        AppVersion::query()->upsert([
            [
                'platform' => 'android',
                'min_version' => '1.0.0',
                'latest_version' => '1.0.0',
                'force_update' => false,
                'store_url' => null,
                'release_notes' => null,
            ],
            [
                'platform' => 'ios',
                'min_version' => '1.0.0',
                'latest_version' => '1.0.0',
                'force_update' => false,
                'store_url' => null,
                'release_notes' => null,
            ],
        ], uniqueBy: ['platform'], update: ['min_version', 'latest_version', 'force_update', 'store_url', 'release_notes']);

        // Default app configs
        $configs = [
            ['key' => 'maintenance_mode', 'value' => 'false', 'type' => 'boolean', 'description' => 'Put the API into maintenance mode (returns 503 for all endpoints)'],
            ['key' => 'maintenance_message', 'value' => 'We are currently performing maintenance. Please try again later.', 'type' => 'string', 'description' => 'Message shown during maintenance mode'],
            ['key' => 'tos_url', 'value' => '', 'type' => 'string', 'description' => 'Terms of Service URL'],
            ['key' => 'privacy_url', 'value' => '', 'type' => 'string', 'description' => 'Privacy Policy URL'],
            ['key' => 'support_email', 'value' => '', 'type' => 'string', 'description' => 'Support contact email'],
            ['key' => 'whatsapp_toyota_service', 'value' => '6285713112000', 'type' => 'string', 'description' => 'Nomor WhatsApp booking servis Toyota dan cek SSC Auto2000 Kertajaya'],
            ['key' => 'whatsapp_otoxpert', 'value' => '6281511060290', 'type' => 'string', 'description' => 'Nomor WhatsApp booking OtoXpert'],
            ['key' => 'whatsapp_body_paint', 'value' => '6285713112000', 'type' => 'string', 'description' => 'Nomor WhatsApp PIC Body & Paint Auto2000 Kertajaya'],
        ];

        foreach ($configs as $config) {
            AppConfig::query()->updateOrCreate(['key' => $config['key']], $config);
        }
    }
}
