<?php

namespace Database\Seeders;

use App\Models\ToyotaServiceLocation;
use App\Models\ToyotaServiceType;
use App\Models\ToyotaThsCoverage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ToyotaServiceMasterSeeder extends Seeder
{
    public function run(): void
    {
        $effectiveFrom = Carbon::parse('2026-07-27', 'Asia/Jakarta')->toDateString();
        $location = ToyotaServiceLocation::query()->updateOrCreate(
            ['code' => 'auto2000-kertajaya'],
            [
                'name' => 'Auto2000 Kertajaya',
                'address' => 'Jl. Kertajaya Indah Timur No. 35',
                'city' => 'Surabaya',
                'phone' => '0315952000',
                'latitude' => null,
                'longitude' => null,
                'directions_url' => 'https://www.google.com/maps/search/?api=1&query=Auto2000+Kertajaya+Surabaya',
                'timezone' => 'Asia/Jakarta',
                'supports_workshop' => true,
                'supports_ths' => true,
                'operating_hours' => [
                    '1' => ['07:00-09:00', '09:00-11:00', '11:00-13:00', '13:00-15:00', '15:00-16:00'],
                    '2' => ['07:00-09:00', '09:00-11:00', '11:00-13:00', '13:00-15:00', '15:00-16:00'],
                    '3' => ['07:00-09:00', '09:00-11:00', '11:00-13:00', '13:00-15:00', '15:00-16:00'],
                    '4' => ['07:00-09:00', '09:00-11:00', '11:00-13:00', '13:00-15:00', '15:00-16:00'],
                    '5' => ['07:00-09:00', '09:00-11:00', '11:00-13:00', '13:00-15:00', '15:00-16:00'],
                    '6' => ['07:00-09:00', '09:00-11:00', '11:00-13:00', '13:00-15:00'],
                    '7' => [],
                ],
                'confirmation_sla_minutes' => 120,
                'cancellation_cutoff_hours' => 4,
                'is_active' => true,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'provenance_url' => 'https://auto2000.co.id/dealer-toyota/surabaya/auto2000surabayakertajaya',
                'verified_at' => Carbon::parse('2026-07-27 12:00:00', 'Asia/Jakarta')->utc(),
            ],
        );

        $serviceTypes = [
            [
                'code' => 'periodic-service',
                'name' => 'Servis Berkala',
                'description' => 'Perawatan berkala kendaraan Toyota sesuai kebutuhan kendaraan.',
                'supports_workshop' => true,
                'supports_ths' => true,
                'workshop_lead_time_days' => 2,
                'ths_lead_time_days' => 1,
                'sort_order' => 10,
            ],
            [
                'code' => 'general-repair',
                'name' => 'General Repair',
                'description' => 'Pemeriksaan dan perbaikan keluhan mekanikal kendaraan.',
                'supports_workshop' => true,
                'supports_ths' => false,
                'workshop_lead_time_days' => 2,
                'ths_lead_time_days' => 1,
                'sort_order' => 20,
            ],
            [
                'code' => 'body-paint',
                'name' => 'Body & Paint',
                'description' => 'Pemeriksaan awal kebutuhan perbaikan bodi dan cat.',
                'supports_workshop' => true,
                'supports_ths' => false,
                'workshop_lead_time_days' => 2,
                'ths_lead_time_days' => 1,
                'sort_order' => 30,
            ],
            [
                'code' => 'consultation',
                'name' => 'Konsultasi / Keluhan Lainnya',
                'description' => 'Konsultasi awal untuk kebutuhan yang belum terwakili layanan lain.',
                'supports_workshop' => true,
                'supports_ths' => false,
                'workshop_lead_time_days' => 2,
                'ths_lead_time_days' => 1,
                'sort_order' => 40,
            ],
        ];

        ToyotaServiceType::query()
            ->where('code', 'toyota-home-service')
            ->update([
                'is_active' => false,
                'effective_to' => $effectiveFrom,
            ]);

        foreach ($serviceTypes as $serviceType) {
            ToyotaServiceType::query()->updateOrCreate(
                ['code' => $serviceType['code']],
                [
                    ...$serviceType,
                    'is_active' => true,
                    'effective_from' => $effectiveFrom,
                    'effective_to' => null,
                ],
            );
        }

        ToyotaThsCoverage::query()->updateOrCreate(
            [
                'service_location_id' => $location->getKey(),
                'city' => 'Surabaya',
            ],
            [
                'latitude_min' => null,
                'latitude_max' => null,
                'longitude_min' => null,
                'longitude_max' => null,
                'is_active' => false,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'verification_source' => 'unverified_pending_configuration',
            ],
        );
    }
}
