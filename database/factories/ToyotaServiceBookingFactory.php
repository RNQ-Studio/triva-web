<?php

namespace Database\Factories;

use App\Models\ToyotaServiceBooking;
use App\Models\ToyotaServiceLocation;
use App\Models\ToyotaServiceType;
use App\Models\Vehicle;
use App\Support\Enums\ToyotaServiceBookingStatus;
use App\Support\Enums\ToyotaServiceContactChannel;
use App\Support\Enums\ToyotaServiceFulfillmentType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ToyotaServiceBooking> */
class ToyotaServiceBookingFactory extends Factory
{
    protected $model = ToyotaServiceBooking::class;

    public function definition(): array
    {
        $primaryStart = now()->addDays(3)->setTime(2, 0);
        $primaryEnd = $primaryStart->copy()->addHours(2);
        $alternativeStart = now()->addDays(4)->setTime(6, 0);
        $alternativeEnd = $alternativeStart->copy()->addHours(2);

        return [
            'vehicle_id' => Vehicle::factory(),
            'user_id' => fn (array $attributes): int => Vehicle::query()
                ->findOrFail($attributes['vehicle_id'])
                ->user_id,
            'service_location_id' => ToyotaServiceLocation::factory(),
            'service_type_id' => ToyotaServiceType::factory(),
            'reference_no' => 'BTS-'.now()->format('Ymd').'-'.strtoupper(Str::random(8)),
            'fulfillment_type' => ToyotaServiceFulfillmentType::Workshop,
            'status' => ToyotaServiceBookingStatus::AwaitingConfirmation,
            'idempotency_key' => (string) Str::uuid(),
            'idempotency_fingerprint' => hash('sha256', Str::random(32)),
            'current_mileage' => 42500,
            'complaint' => 'Rem terasa bergetar ketika kendaraan melaju.',
            'primary_start_at' => $primaryStart,
            'primary_end_at' => $primaryEnd,
            'alternative_start_at' => $alternativeStart,
            'alternative_end_at' => $alternativeEnd,
            'active_slot_start_at' => $primaryStart,
            'active_slot_end_at' => $primaryEnd,
            'contact_channel' => ToyotaServiceContactChannel::Whatsapp,
            'submitted_at' => now(),
            'due_at' => now()->addHours(2),
            'last_status_changed_at' => now(),
        ];
    }
}
