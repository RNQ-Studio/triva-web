<?php

namespace Database\Factories;

use App\Models\ToyotaServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ToyotaServiceType> */
class ToyotaServiceTypeFactory extends Factory
{
    protected $model = ToyotaServiceType::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'supports_workshop' => true,
            'supports_ths' => true,
            'workshop_lead_time_days' => 2,
            'ths_lead_time_days' => 1,
            'sort_order' => 10,
            'is_active' => true,
            'effective_from' => now()->subDay()->toDateString(),
        ];
    }
}
