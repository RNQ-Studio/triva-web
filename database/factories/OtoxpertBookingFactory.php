<?php

namespace Database\Factories;

use App\Models\OtoxpertBooking;
use App\Models\OtoxpertService;
use App\Models\OtoxpertWorkshop;
use App\Models\Vehicle;
use App\Support\Enums\OtoxpertBookingStatus;
use App\Support\Enums\ToyotaServiceContactChannel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<OtoxpertBooking> */
class OtoxpertBookingFactory extends Factory
{
    protected $model = OtoxpertBooking::class;

    public function definition(): array
    {
        $primaryStart = now()->addDays(3)->setTime(1, 0);
        $primaryEnd = $primaryStart->copy()->addHours(2);
        $alternativeStart = now()->addDays(4)->setTime(3, 0);
        $alternativeEnd = $alternativeStart->copy()->addHours(2);

        return [
            'vehicle_id' => Vehicle::factory(),
            'user_id' => fn (array $attributes): int => Vehicle::query()
                ->findOrFail($attributes['vehicle_id'])
                ->user_id,
            'workshop_id' => OtoxpertWorkshop::factory(),
            'service_id' => OtoxpertService::factory(),
            'reference_no' => 'OX-'.now()->format('ymd').'-'
                .strtoupper(Str::random(8)),
            'status' => OtoxpertBookingStatus::AwaitingConfirmation,
            'current_mileage' => 42500,
            'complaint' => 'Kendaraan bergetar saat kecepatan meningkat.',
            'symptom_codes' => ['vibration'],
            'pickup_delivery_requested' => false,
            'contact_channel' => ToyotaServiceContactChannel::Whatsapp,
            'primary_start_at' => $primaryStart,
            'primary_end_at' => $primaryEnd,
            'alternative_start_at' => $alternativeStart,
            'alternative_end_at' => $alternativeEnd,
            'partner_consent_at' => now(),
            'partner_consent_version' => 'otoxpert-data-sharing-v1',
            'idempotency_key' => (string) Str::uuid(),
            'request_fingerprint' => hash('sha256', Str::random(32)),
            'submitted_at' => now(),
            'due_at' => now()->addMinutes(30),
            'last_status_changed_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (OtoxpertBooking $booking): void {
            $booking->workshop->services()->syncWithoutDetaching([
                $booking->service_id => [
                    'lead_time_days' => 1,
                    'is_active' => true,
                ],
            ]);
        });
    }
}
