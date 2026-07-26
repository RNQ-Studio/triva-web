<?php

namespace Database\Seeders;

use App\Models\VehicleMake;
use Illuminate\Database\Seeder;

class VehicleMakeSeeder extends Seeder
{
    public function run(): void
    {
        $makes = [
            ['aion', 'AION'],
            ['audi', 'Audi'],
            ['baic', 'BAIC'],
            ['bmw', 'BMW'],
            ['byd', 'BYD'],
            ['chery', 'Chery'],
            ['citroen', 'Citroën'],
            ['daihatsu', 'Daihatsu'],
            ['dfsk', 'DFSK'],
            ['ford', 'Ford'],
            ['geely', 'Geely'],
            ['gwm', 'GWM'],
            ['honda', 'Honda'],
            ['hyundai', 'Hyundai'],
            ['isuzu', 'Isuzu'],
            ['jeep', 'Jeep'],
            ['jetour', 'Jetour'],
            ['kia', 'Kia'],
            ['lexus', 'Lexus'],
            ['mazda', 'Mazda'],
            ['mercedes-benz', 'Mercedes-Benz'],
            ['mg', 'MG'],
            ['mini', 'MINI'],
            ['mitsubishi', 'Mitsubishi'],
            ['nissan', 'Nissan'],
            ['subaru', 'Subaru'],
            ['suzuki', 'Suzuki'],
            ['toyota', 'Toyota'],
            ['vinfast', 'VinFast'],
            ['volkswagen', 'Volkswagen'],
            ['volvo', 'Volvo'],
            ['wuling', 'Wuling'],
            ['xpeng', 'XPeng'],
        ];

        foreach ($makes as $index => [$slug, $name]) {
            VehicleMake::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'logo_path' => "images/vehicle-makes/{$slug}.png",
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }
}
