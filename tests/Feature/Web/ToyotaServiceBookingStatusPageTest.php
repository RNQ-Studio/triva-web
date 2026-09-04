<?php

namespace Tests\Feature\Web;

use App\Models\Notification;
use App\Models\ToyotaServiceBooking;
use App\Models\ToyotaServiceBookingStatusHistory;
use App\Support\Enums\ToyotaServiceBookingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ToyotaServiceBookingStatusPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_every_booking_gets_a_public_token_and_a_status_link(): void
    {
        $booking = ToyotaServiceBooking::factory()->create();

        self::assertTrue(Str::isUuid((string) $booking->public_token));
        self::assertSame(
            route('toyota-service.status', $booking->public_token),
            $booking->statusUpdateUrl(),
        );
    }

    public function test_status_page_opens_without_login_and_shows_the_booking(): void
    {
        $booking = ToyotaServiceBooking::factory()->create();

        $this->get($booking->statusUpdateUrl())
            ->assertOk()
            ->assertViewIs('toyota-service.status')
            ->assertSee($booking->reference_no)
            ->assertSee('Menunggu konfirmasi petugas')
            ->assertSee('Tandai Sedang Diproses')
            ->assertSee('Tandai Selesai');
    }

    public function test_unknown_or_malformed_tokens_are_not_found(): void
    {
        ToyotaServiceBooking::factory()->create();

        $this->get('/booking-servis/'.Str::uuid())->assertNotFound();
        $this->get('/booking-servis/bukan-token')->assertNotFound();
    }

    public function test_marking_processing_moves_the_booking_into_service_and_notifies_the_customer(): void
    {
        $booking = ToyotaServiceBooking::factory()->create();

        $this->post(route('toyota-service.status.update', $booking->public_token), [
            'stage' => 'processing',
        ])
            ->assertRedirect($booking->statusUpdateUrl())
            ->assertSessionHas('success');

        $booking->refresh();
        self::assertSame(ToyotaServiceBookingStatus::InService, $booking->status);
        self::assertNotNull($booking->confirmed_at);
        self::assertTrue($booking->confirmed_start_at->equalTo($booking->primary_start_at));
        self::assertDatabaseHas(ToyotaServiceBookingStatusHistory::class, [
            'service_booking_id' => $booking->getKey(),
            'event' => 'service_started',
            'actor_type' => 'staff',
        ]);
        self::assertDatabaseHas(Notification::class, [
            'user_id' => $booking->user_id,
            'type' => 'toyota_service_booking',
            'title' => 'Servis dimulai',
        ]);

        $this->get($booking->statusUpdateUrl())
            ->assertOk()
            ->assertSee('Sedang dikerjakan')
            ->assertDontSee('Tandai Sedang Diproses')
            ->assertSee('Tandai Selesai');
    }

    public function test_marking_completed_closes_the_booking(): void
    {
        $booking = ToyotaServiceBooking::factory()->create([
            'status' => ToyotaServiceBookingStatus::InService,
        ]);

        $this->post(route('toyota-service.status.update', $booking->public_token), [
            'stage' => 'completed',
        ])->assertRedirect($booking->statusUpdateUrl());

        $booking->refresh();
        self::assertSame(ToyotaServiceBookingStatus::Completed, $booking->status);
        self::assertNotNull($booking->completed_at);

        $this->get($booking->statusUpdateUrl())
            ->assertOk()
            ->assertSee('Servis selesai')
            ->assertDontSee('Tandai Selesai');
    }

    public function test_closed_bookings_cannot_be_advanced(): void
    {
        $booking = ToyotaServiceBooking::factory()->create([
            'status' => ToyotaServiceBookingStatus::Cancelled,
        ]);

        $this->get($booking->statusUpdateUrl())
            ->assertOk()
            ->assertSee('sudah ditutup')
            ->assertDontSee('Tandai Selesai');

        $this->post(route('toyota-service.status.update', $booking->public_token), [
            'stage' => 'completed',
        ])
            ->assertRedirect($booking->statusUpdateUrl())
            ->assertSessionHas('error');

        self::assertSame(ToyotaServiceBookingStatus::Cancelled, $booking->refresh()->status);
    }

    public function test_unknown_stage_is_rejected(): void
    {
        $booking = ToyotaServiceBooking::factory()->create();

        $this->from($booking->statusUpdateUrl())
            ->post(route('toyota-service.status.update', $booking->public_token), [
                'stage' => 'waiting',
            ])
            ->assertSessionHasErrors('stage');
    }
}
