<?php

namespace Database\Factories;

use App\Models\Appraisal;
use App\Models\Vehicle;
use App\Support\Enums\AppraisalStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Appraisal> */
class AppraisalFactory extends Factory
{
    protected $model = Appraisal::class;

    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'user_id' => fn (array $attributes): int => Vehicle::query()
                ->findOrFail($attributes['vehicle_id'])
                ->user_id,
            'reference_no' => 'TIA-'.now()->format('Ymd').'-'.str_pad(
                (string) fake()->unique()->numberBetween(1, 99999999),
                8,
                '0',
                STR_PAD_LEFT,
            ),
            'status' => AppraisalStatus::Draft,
        ];
    }
}
