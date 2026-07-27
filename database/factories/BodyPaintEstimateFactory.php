<?php

namespace Database\Factories;

use App\Models\BodyPaintEstimate;
use App\Models\ToyotaServiceLocation;
use App\Models\Vehicle;
use App\Support\Enums\BodyPaintEstimateStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<BodyPaintEstimate> */
class BodyPaintEstimateFactory extends Factory
{
    protected $model = BodyPaintEstimate::class;

    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'user_id' => fn (array $attributes): int => Vehicle::query()
                ->findOrFail($attributes['vehicle_id'])
                ->user_id,
            'service_location_id' => ToyotaServiceLocation::factory(),
            'reference_no' => 'BPE-'.now()->format('Ymd').'-'
                .strtoupper(Str::random(8)),
            'status' => BodyPaintEstimateStatus::Draft,
            'idempotency_key' => (string) Str::uuid(),
            'request_fingerprint' => hash('sha256', Str::random(32)),
            'requires_physical_inspection' => true,
            'last_status_changed_at' => now(),
        ];
    }
}
