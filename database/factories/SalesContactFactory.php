<?php

namespace Database\Factories;

use App\Models\SalesContact;
use App\Support\Enums\SalesContactRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SalesContact> */
class SalesContactFactory extends Factory
{
    protected $model = SalesContact::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'role' => SalesContactRole::Sales,
            'whatsapp_number' => '6281'.fake()->numerify('#########'),
            'photo_path' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function spv(): static
    {
        return $this->state(fn (): array => ['role' => SalesContactRole::Spv]);
    }
}
