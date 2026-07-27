<?php

namespace Database\Factories;

use App\Models\OtoxpertService;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OtoxpertService> */
class OtoxpertServiceFactory extends Factory
{
    protected $model = OtoxpertService::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'default_lead_time_days' => 1,
            'sort_order' => 10,
            'is_active' => true,
            'effective_from' => now()->subDay()->toDateString(),
            'effective_to' => null,
        ];
    }
}
