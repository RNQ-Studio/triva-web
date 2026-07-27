<?php

namespace Tests\Feature\Console;

use App\Models\ToyotaServiceBooking;
use App\Support\Enums\ToyotaServiceBookingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ToyotaServiceAlternativeExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-27 01:00:00', 'UTC'));
        Queue::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_command_expires_initial_proposal_but_restores_confirmed_reschedule(): void
    {
        $initial = ToyotaServiceBooking::factory()->create([
            'status' => ToyotaServiceBookingStatus::AlternativeProposed,
            'proposed_start_at' => now()->addDay(),
            'proposed_end_at' => now()->addDay()->addHours(2),
            'proposal_context' => 'initial',
            'proposal_reason' => 'Jadwal awal penuh.',
            'proposal_expires_at' => now()->subMinute(),
        ]);
        $reschedule = ToyotaServiceBooking::factory()->create([
            'status' => ToyotaServiceBookingStatus::AlternativeProposed,
            'confirmed_start_at' => now()->addDays(3),
            'confirmed_end_at' => now()->addDays(3)->addHours(2),
            'confirmed_at' => now()->subDay(),
            'proposed_start_at' => now()->addDays(4),
            'proposed_end_at' => now()->addDays(4)->addHours(2),
            'proposal_context' => 'reschedule',
            'proposal_reason' => 'Jadwal ulang alternatif.',
            'proposal_expires_at' => now()->subMinute(),
        ]);

        $this->artisan('toyota-service:expire-alternatives')
            ->expectsOutput('Reconciled 2 expired Toyota service alternative(s).')
            ->assertSuccessful();

        $this->assertSame(ToyotaServiceBookingStatus::Expired, $initial->refresh()->status);
        $this->assertSame(ToyotaServiceBookingStatus::Confirmed, $reschedule->refresh()->status);
        $this->assertNotNull($reschedule->confirmed_start_at);
        $this->assertDatabaseHas('toyota_service_booking_status_histories', [
            'service_booking_id' => $initial->getKey(),
            'event' => 'alternative_expired',
            'actor_type' => 'system',
        ]);
        $this->assertDatabaseHas('toyota_service_booking_status_histories', [
            'service_booking_id' => $reschedule->getKey(),
            'event' => 'reschedule_alternative_expired',
            'actor_type' => 'system',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $initial->user_id,
            'type' => 'toyota_service_booking',
        ]);
    }
}
