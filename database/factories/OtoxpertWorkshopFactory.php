<?php

namespace Database\Factories;

use App\Models\OtoxpertWorkshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OtoxpertWorkshop> */
class OtoxpertWorkshopFactory extends Factory
{
    protected $model = OtoxpertWorkshop::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'partner_code' => fake()->unique()->bothify('partner-####'),
            'name' => 'OtoXpert '.fake()->city(),
            'address' => fake()->address(),
            'province' => 'Jawa Timur',
            'city' => 'Surabaya',
            'phone' => '08111060290',
            'timezone' => 'Asia/Jakarta',
            'operating_hours' => [
                '1' => ['08:00-10:00', '10:00-12:00', '13:00-15:00'],
                '2' => ['08:00-10:00', '10:00-12:00', '13:00-15:00'],
                '3' => ['08:00-10:00', '10:00-12:00', '13:00-15:00'],
                '4' => ['08:00-10:00', '10:00-12:00', '13:00-15:00'],
                '5' => ['08:00-10:00', '10:00-12:00', '13:00-15:00'],
                '6' => ['08:00-10:00', '10:00-12:00', '13:00-15:00'],
                '7' => [],
            ],
            'supports_all_vehicle_makes' => true,
            'supports_pickup_delivery' => false,
            'confirmation_sla_minutes' => 30,
            'cancellation_cutoff_hours' => 4,
            'is_active' => true,
            'effective_from' => now()->subDay()->toDateString(),
            'effective_to' => null,
            'provenance_url' => 'https://otoxpert.co.id/workshop',
            'verified_at' => now(),
        ];
    }
}
