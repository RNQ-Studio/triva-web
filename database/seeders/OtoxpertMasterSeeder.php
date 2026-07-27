<?php

namespace Database\Seeders;

use App\Models\OtoxpertService;
use App\Models\OtoxpertWorkshop;
use App\Models\OtoxpertWorkshopServicePrice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class OtoxpertMasterSeeder extends Seeder
{
    public function run(): void
    {
        $effectiveFrom = '2026-07-27';
        $verifiedAt = Carbon::parse(
            '2026-07-27 21:30:00',
            'Asia/Jakarta',
        )->utc();
        $source = 'https://otoxpert.co.id/workshop?page=3';
        $workshops = [
            [
                'code' => 'otoxpert-dukuh-kupang',
                'partner_code' => 'official-dukuh-kupang',
                'name' => 'OtoXpert Dukuh Kupang',
                'address' => 'Jl. Raya Dukuh Kupang No.141-143, Pakis, Kec. Sawahan, Kota Surabaya, Jawa Timur 60256',
                'phone' => '0315624999',
                'operating_hours' => [
                    '1' => ['08:00-10:00', '10:00-12:00', '13:00-15:00'],
                    '2' => ['08:00-10:00', '10:00-12:00', '13:00-15:00'],
                    '3' => ['08:00-10:00', '10:00-12:00', '13:00-15:00'],
                    '4' => ['08:00-10:00', '10:00-12:00', '13:00-15:00'],
                    '5' => ['08:00-10:00', '10:00-12:00', '13:00-15:00'],
                    '6' => ['08:00-10:00', '10:00-12:00', '13:00-15:00'],
                    '7' => [],
                ],
            ],
            [
                'code' => 'otoxpert-rungkut',
                'partner_code' => 'official-rungkut-auto2000',
                'name' => 'OtoXpert Rungkut',
                'address' => 'Jl. Rungkut Madya No.151, Rungkut Kidul, Kec. Rungkut, Kota Surabaya, Jawa Timur 60293',
                'phone' => '08111060290',
                'operating_hours' => [
                    '1' => ['08:00-10:00', '10:00-12:00', '13:00-15:00', '15:00-17:00'],
                    '2' => ['08:00-10:00', '10:00-12:00', '13:00-15:00', '15:00-17:00'],
                    '3' => ['08:00-10:00', '10:00-12:00', '13:00-15:00', '15:00-17:00'],
                    '4' => ['08:00-10:00', '10:00-12:00', '13:00-15:00', '15:00-17:00'],
                    '5' => ['08:00-10:00', '10:00-12:00', '13:00-15:00', '15:00-17:00'],
                    '6' => ['08:00-10:00', '10:00-12:00', '13:00-15:00', '15:00-17:00'],
                    '7' => ['08:00-10:00', '10:00-12:00', '13:00-15:00', '15:00-17:00'],
                ],
            ],
        ];
        $savedWorkshops = [];
        foreach ($workshops as $item) {
            $savedWorkshops[] = OtoxpertWorkshop::query()->updateOrCreate(
                ['code' => $item['code']],
                [
                    ...$item,
                    'province' => 'Jawa Timur',
                    'city' => 'Surabaya',
                    'latitude' => null,
                    'longitude' => null,
                    'timezone' => 'Asia/Jakarta',
                    'service_radius_km' => null,
                    'supports_all_vehicle_makes' => true,
                    'supports_pickup_delivery' => false,
                    'confirmation_sla_minutes' => 30,
                    'cancellation_cutoff_hours' => 4,
                    'is_active' => true,
                    'effective_from' => $effectiveFrom,
                    'effective_to' => null,
                    'provenance_url' => $source,
                    'verified_at' => $verifiedAt,
                ],
            );
        }

        $services = [
            ['ganti-oli', 'Ganti Oli', 'Penggantian oli mesin, gasket, dan filter oli.', 10, 299000, 'https://otoxpert.co.id/parts-and-services/service/ganti-oli'],
            ['aki', 'Aki', 'Pemeriksaan dan penggantian aki kendaraan.', 20, 696000, 'https://otoxpert.co.id/parts-and-services/service/aki'],
            ['servis-ringan', 'Servis Ringan', 'Perawatan ringan dan pemeriksaan kendaraan.', 30, 654000, 'https://otoxpert.co.id/parts-and-services/service/service-ringan'],
            ['servis-lengkap', 'Servis Lengkap', 'Perawatan lengkap dan pemeriksaan menyeluruh.', 40, 1363000, 'https://otoxpert.co.id/parts-and-services/service/servis-lengkap'],
            ['tune-up', 'Tune Up', 'Pemeriksaan dan penyetelan performa mesin.', 50, 396000, 'https://otoxpert.co.id/parts-and-services/service/tune-up'],
            ['rem', 'Rem', 'Pemeriksaan dan perawatan sistem pengereman.', 60, null, null],
            ['ban', 'Ban', 'Pemeriksaan, perawatan, atau penggantian ban.', 70, null, null],
            ['shock-absorber', 'Shock Absorber', 'Pemeriksaan dan perawatan suspensi.', 80, null, null],
            ['keluhan-lainnya', 'Keluhan Lainnya', 'Pemeriksaan awal untuk keluhan lain.', 90, null, null],
        ];
        foreach ($services as [$code, $name, $description, $sort, $price, $priceSource]) {
            $service = OtoxpertService::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'description' => $description,
                    'default_lead_time_days' => 1,
                    'sort_order' => $sort,
                    'is_active' => true,
                    'effective_from' => $effectiveFrom,
                    'effective_to' => null,
                ],
            );
            foreach ($savedWorkshops as $workshop) {
                $workshop->services()->syncWithoutDetaching([
                    $service->getKey() => [
                        'lead_time_days' => 1,
                        'is_active' => true,
                    ],
                ]);
                if ($price !== null && $priceSource !== null) {
                    OtoxpertWorkshopServicePrice::query()->updateOrCreate(
                        [
                            'workshop_id' => $workshop->getKey(),
                            'service_id' => $service->getKey(),
                            'effective_from' => $effectiveFrom,
                        ],
                        [
                            'price_type' => 'from',
                            'minimum_amount' => $price,
                            'maximum_amount' => null,
                            'currency' => 'IDR',
                            'included_items' => null,
                            'excluded_items' => null,
                            'disclaimer' => 'Harga mulai dari, bervariasi menurut lokasi dan kondisi kendaraan. Penawaran diberikan bengkel sebelum layanan.',
                            'effective_to' => null,
                            'is_active' => true,
                            'source_url' => $priceSource,
                            'verified_at' => $verifiedAt,
                        ],
                    );
                }
            }
        }
    }
}
