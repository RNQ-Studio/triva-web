<?php

namespace Database\Factories;

use App\Models\ToyotaServiceLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ToyotaServiceLocation> */
class ToyotaServiceLocationFactory extends Factory
{
    protected $model = ToyotaServiceLocation::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => 'Auto2000 '.fake()->city(),
            'address' => fake()->address(),
            'city' => 'Surabaya',
            'phone' => '0315952000',
            'timezone' => 'Asia/Jakarta',
            'supports_workshop' => true,
            'supports_ths' => true,
            'operating_hours' => [
                '1' => ['09:00-11:00', '13:00-15:00'],
                '2' => ['09:00-11:00', '13:00-15:00'],
                '3' => ['09:00-11:00', '13:00-15:00'],
                '4' => ['09:00-11:00', '13:00-15:00'],
                '5' => ['09:00-11:00', '13:00-15:00'],
                '6' => ['09:00-11:00'],
                '7' => [],
            ],
            'confirmation_sla_minutes' => 120,
            'cancellation_cutoff_hours' => 4,
            'is_active' => true,
            'effective_from' => now()->subDay()->toDateString(),
        ];
    }
}
