<?php

namespace Database\Factories;

use App\Models\VisitEvent;
use App\Support\Enums\VisitSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VisitEvent> */
class VisitEventFactory extends Factory
{
    protected $model = VisitEvent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'source' => fake()->randomElement(VisitSource::cases()),
            'visit_key' => hash('sha256', fake()->unique()->uuid()),
            'occurred_at' => now(),
            'app_version' => fake()->optional()->semver(),
            'app_build' => fake()->optional()->numerify('###'),
        ];
    }
}
