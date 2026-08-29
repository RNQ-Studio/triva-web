<?php

namespace Database\Factories;

use App\Models\MenuUsageEvent;
use App\Support\Enums\MenuKey;
use App\Support\Enums\VisitSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MenuUsageEvent> */
class MenuUsageEventFactory extends Factory
{
    protected $model = MenuUsageEvent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'menu_key' => fake()->randomElement(MenuKey::values()),
            'source' => fake()->randomElement(VisitSource::clientValues()),
            'occurred_at' => now(),
            'app_version' => fake()->optional()->semver(),
            'app_build' => fake()->optional()->numerify('###'),
        ];
    }
}
