<?php

namespace Tests\Feature\Api;

use App\Models\ToyotaServiceBooking;
use App\Models\ToyotaServiceLocation;
use App\Models\ToyotaServiceType;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ToyotaServiceMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ToyotaServiceAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $admin;

    private Vehicle $vehicle;

    private ToyotaServiceLocation $location;

    private ToyotaServiceType $serviceType;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-27 01:00:00', 'UTC'));
        Queue::fake();
        $this->seed([
            RolePermissionSeeder::class,
            ToyotaServiceMasterSeeder::class,
        ]);
        $this->customer = User::factory()->create([
            'phone' => '+6281234567890',
            'city' => 'Surabaya',
            'service_consent_at' => now(),
        ]);
        $this->admin = User::factory()->create(['phone' => '0315952000']);
        $this->admin->assignRole('admin');
        $this->vehicle = Vehicle::factory()->create([
            'user_id' => $this->customer->getKey(),
            'make' => 'Toyota',
        ]);
        $this->location = ToyotaServiceLocation::query()
            ->where('code', 'auto2000-kertajaya')
            ->firstOrFail();
        $this->serviceType = ToyotaServiceType::query()
            ->where('code', 'periodic-service')
            ->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_mobile_admin_routes_require_permission_for_queue_detail_and_action(): void
    {
        Passport::actingAs($this->customer);
        $booking = $this->createBooking();

        Passport::actingAs($this->customer);
        $this->getJson('/api/v1/admin/toyota-service/options')->assertForbidden();
        $this->getJson('/api/v1/admin/toyota-service/bookings')->assertForbidden();
        $this->getJson("/api/v1/admin/toyota-service/bookings/{$booking->id}")
            ->assertForbidden();
        $this->postJson("/api/v1/admin/toyota-service/bookings/{$booking->id}/actions", [
            'action' => 'cancel',
            'reason_code' => 'operational',
            'reason' => 'Tidak dapat diproses.',
        ])->assertForbidden();

        Passport::actingAs($this->admin);
        $this->getJson('/api/v1/admin/toyota-service/options')
            ->assertOk()
            ->assertJsonPath('data.advisors.0.id', $this->admin->getKey())
            ->assertJsonPath('data.statuses.0.value', 'awaiting_confirmation')
            ->assertJsonPath('data.actions.0.value', 'assign')
            ->assertJsonCount(4, 'data.service_types')
            ->assertJsonMissing(['id' => $this->customer->getKey()]);
        $this->getJson('/api/v1/admin/toyota-service/bookings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer.id', $this->customer->getKey())
            ->assertJsonPath('data.0.available_admin_actions.0.action', 'assign');
        $this->getJson("/api/v1/admin/toyota-service/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $booking->id);

        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => ''])
            ->getJson('/api/v1/admin/toyota-service/bookings')
            ->assertUnauthorized();
    }

    public function test_queue_filters_sla_and_rejects_invalid_assignee(): void
    {
        Passport::actingAs($this->customer);
        $booking = $this->createBooking();
        $booking->update(['due_at' => now()->subMinute()]);
        Passport::actingAs($this->admin);

        $this->getJson('/api/v1/admin/toyota-service/bookings?sla_status=overdue')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sla.is_overdue', true);
        $this->getJson('/api/v1/admin/toyota-service/bookings?status=confirmed')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $booking->update([
            'active_slot_start_at' => Carbon::parse('2026-07-28 17:30:00', 'UTC'),
            'active_slot_end_at' => Carbon::parse('2026-07-28 19:30:00', 'UTC'),
        ]);
        $this->getJson('/api/v1/admin/toyota-service/bookings?date=2026-07-29')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/toyota-service/bookings?date=2026-07-28')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $activeCustomer = User::factory()->create();
        $this->postJson("/api/v1/admin/toyota-service/bookings/{$booking->id}/actions", [
            'action' => 'assign',
            'advisor_id' => $activeCustomer->getKey(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['advisor_id']);

        $advisor = User::factory()->create();
        $advisor->assignRole('staff');
        $this->postJson("/api/v1/admin/toyota-service/bookings/{$booking->id}/actions", [
            'action' => 'assign',
            'advisor_id' => $advisor->getKey(),
        ])
            ->assertOk()
            ->assertJsonPath('data.assigned_advisor.id', $advisor->getKey());
    }

    public function test_queue_sort_is_stable_across_pages_when_primary_values_are_equal(): void
    {
        $ids = [
            '00000000-0000-4000-8000-000000000001',
            '00000000-0000-4000-8000-000000000002',
            '00000000-0000-4000-8000-000000000003',
        ];
        $sharedSlot = now()->addDays(3)->setTime(2, 0);

        foreach (array_reverse($ids) as $id) {
            ToyotaServiceBooking::factory()->create([
                'id' => $id,
                'service_location_id' => $this->location->getKey(),
                'service_type_id' => $this->serviceType->getKey(),
                'due_at' => now()->addHours(2),
                'active_slot_start_at' => $sharedSlot,
                'active_slot_end_at' => $sharedSlot->copy()->addHours(2),
            ]);
        }

        Passport::actingAs($this->admin);

        foreach (['updated_desc', 'due_asc', 'slot_asc'] as $sort) {
            $pageOne = $this->getJson(
                "/api/v1/admin/toyota-service/bookings?sort={$sort}&per_page=2&page=1",
            )->assertOk();
            $pageTwo = $this->getJson(
                "/api/v1/admin/toyota-service/bookings?sort={$sort}&per_page=2&page=2",
            )->assertOk();

            $actual = [
                ...$pageOne->json('data'),
                ...$pageTwo->json('data'),
            ];

            $this->assertSame(
                $ids,
                array_column($actual, 'id'),
                "Sort {$sort} must not duplicate or skip equal-valued rows.",
            );
        }
    }

    public function test_admin_actions_validate_confirmation_and_illegal_transition(): void
    {
        Passport::actingAs($this->customer);
        $booking = $this->createBooking();
        Passport::actingAs($this->admin);

        $this->postJson("/api/v1/admin/toyota-service/bookings/{$booking->id}/actions", [
            'action' => 'confirm',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'confirmed_slot',
                'pic_name',
                'arrival_instructions',
            ]);

        $this->postJson("/api/v1/admin/toyota-service/bookings/{$booking->id}/actions", [
            'action' => 'complete',
        ])
            ->assertConflict()
            ->assertJsonPath('code', 'TOYOTA_SERVICE_STATE_CONFLICT');

        $this->postJson("/api/v1/admin/toyota-service/bookings/{$booking->id}/actions", [
            'action' => 'reject',
            'reason_code' => 'arbitrary_untrusted_code',
            'reason' => 'Kode alasan ini tidak terdaftar.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason_code']);

        $this->postJson("/api/v1/admin/toyota-service/bookings/{$booking->id}/actions", [
            'action' => 'confirm',
            'confirmed_slot' => ['date' => '2026-07-31', 'time_window' => '09:00-11:00'],
            'pic_name' => 'Service Advisor Kertajaya',
            'arrival_instructions' => 'Datang 15 menit sebelum jadwal.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['confirmed_slot']);

        $this->postJson("/api/v1/admin/toyota-service/bookings/{$booking->id}/actions", [
            'action' => 'propose_alternative',
            'proposed_slot' => ['date' => '2026-07-31', 'time_window' => '09:00-11:00'],
            'proposal_reason' => 'Pilihan awal tidak tersedia.',
            'proposal_expires_at' => '2026-07-31T02:00:00Z',
            'pic_name' => 'Service Advisor Kertajaya',
            'arrival_instructions' => 'Datang 15 menit sebelum jadwal.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['proposal_expires_at']);

        $this->confirm($booking);
        $this->postJson("/api/v1/admin/toyota-service/bookings/{$booking->id}/actions", [
            'action' => 'propose_alternative',
            'proposed_slot' => ['date' => '2026-07-31', 'time_window' => '09:00-11:00'],
            'proposal_reason' => 'Usulan jadwal ulang tidak boleh memblokir jadwal lama.',
            'proposal_expires_at' => '2026-07-29T02:00:00Z',
            'pic_name' => 'Service Advisor Kertajaya',
            'arrival_instructions' => 'Instruksi usulan baru.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['proposal_expires_at']);
        $this->postJson("/api/v1/admin/toyota-service/bookings/{$booking->id}/actions", [
            'action' => 'check_in',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'checked_in');
        $this->postJson("/api/v1/admin/toyota-service/bookings/{$booking->id}/actions", [
            'action' => 'start_service',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_service');
        $completionSecret = 'Catatan penyelesaian internal: margin dan keputusan bengkel.';
        $completed = $this->postJson("/api/v1/admin/toyota-service/bookings/{$booking->id}/actions", [
            'action' => 'complete',
            'note' => $completionSecret,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.timeline.4.event', 'service_completed');
        $this->assertStringContainsString($completionSecret, $completed->getContent());

        Passport::actingAs($this->customer);
        $customerView = $this->getJson("/api/v1/toyota-service/bookings/{$booking->id}")
            ->assertOk();
        $this->assertStringNotContainsString($completionSecret, $customerView->getContent());
    }

    public function test_admin_can_confirm_prevalidated_requested_slot_after_lead_time_has_elapsed(): void
    {
        Passport::actingAs($this->customer);
        $booking = $this->createBooking();
        Carbon::setTestNow(Carbon::parse('2026-07-28 01:00:00', 'UTC'));
        Passport::actingAs($this->admin);

        $this->confirm($booking);
        $this->assertDatabaseHas('toyota_service_bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_admin_cannot_confirm_a_requested_slot_after_it_has_started(): void
    {
        Passport::actingAs($this->customer);
        $booking = $this->createBooking();
        Carbon::setTestNow(Carbon::parse('2026-07-29 03:01:00', 'UTC'));
        Passport::actingAs($this->admin);

        $this->postJson("/api/v1/admin/toyota-service/bookings/{$booking->id}/actions", [
            'action' => 'confirm',
            'confirmed_slot' => ['date' => '2026-07-29', 'time_window' => '09:00-11:00'],
            'pic_name' => 'Service Advisor Kertajaya',
            'arrival_instructions' => 'Datang 15 menit sebelum jadwal.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['confirmed_slot.date']);
    }

    public function test_internal_confirmation_note_is_not_exposed_to_customer_timeline(): void
    {
        Passport::actingAs($this->customer);
        $booking = $this->createBooking();
        Passport::actingAs($this->admin);
        $secret = 'Catatan audit internal tidak boleh bocor ke pelanggan.';

        $response = $this->postJson(
            "/api/v1/admin/toyota-service/bookings/{$booking->id}/actions",
            [
                'action' => 'confirm',
                'confirmed_slot' => ['date' => '2026-07-29', 'time_window' => '09:00-11:00'],
                'pic_name' => 'Service Advisor Kertajaya',
                'arrival_instructions' => 'Datang 15 menit sebelum jadwal.',
                'note' => $secret,
            ],
        )->assertOk();
        $this->assertStringContainsString($secret, $response->getContent());
        $this->assertDatabaseHas('toyota_service_booking_status_histories', [
            'service_booking_id' => $booking->getKey(),
            'event' => 'confirmation_internal_note',
            'description' => $secret,
            'user_visible' => false,
        ]);

        Passport::actingAs($this->customer);
        $customer = $this->getJson("/api/v1/toyota-service/bookings/{$booking->id}")
            ->assertOk();
        $this->assertStringNotContainsString($secret, $customer->getContent());
    }

    public function test_benefit_verification_requires_source_and_records_actor(): void
    {
        Passport::actingAs($this->customer);
        $booking = $this->createBooking();
        Passport::actingAs($this->admin);

        $this->postJson("/api/v1/admin/toyota-service/bookings/{$booking->id}/actions", [
            'action' => 'verify_benefit',
            'benefit_type' => 't_care',
            'benefit_status' => 'pending_verification',
        ])
            ->assertOk()
            ->assertJsonPath('data.benefit_checks.0.status', 'pending_verification')
            ->assertJsonPath('data.benefit_checks.0.verification_source', null);

        $this->postJson("/api/v1/admin/toyota-service/bookings/{$booking->id}/actions", [
            'action' => 'verify_benefit',
            'benefit_type' => 't_care',
            'benefit_status' => 'active',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['verification_source']);

        $benefitSecret = 'Bukti internal nomor dokumen BENEFIT-SECRET-123.';
        $adminResponse = $this->postJson("/api/v1/admin/toyota-service/bookings/{$booking->id}/actions", [
            'action' => 'verify_benefit',
            'benefit_type' => 't_care',
            'benefit_status' => 'active',
            'verification_source' => 'staff_manual',
            'benefit_valid_until' => '2027-07-27T00:00:00Z',
            'benefit_notes' => $benefitSecret,
        ])
            ->assertOk()
            ->assertJsonPath('data.benefit_checks.0.status', 'active')
            ->assertJsonPath('data.benefit_checks.0.verification_source', 'staff_manual')
            ->assertJsonPath('data.benefit_checks.0.verified_by.id', $this->admin->getKey())
            ->assertJsonPath('data.benefit_checks.0.notes', $benefitSecret);

        $this->assertDatabaseHas('vehicle_benefit_checks', [
            'service_booking_id' => $booking->getKey(),
            'benefit_type' => 't_care',
            'status' => 'active',
            'verification_source' => 'staff_manual',
            'verified_by' => $this->admin->getKey(),
        ]);

        Passport::actingAs($this->customer);
        $customerResponse = $this->getJson("/api/v1/toyota-service/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.benefit_checks.0.notes')
            ->assertJsonMissingPath('data.benefit_checks.0.recorded_status')
            ->assertJsonMissingPath('data.benefit_checks.0.verified_by');
        $this->assertStringNotContainsString($benefitSecret, $customerResponse->getContent());
    }

    public function test_no_show_cannot_be_recorded_before_confirmed_window_ends(): void
    {
        Passport::actingAs($this->customer);
        $booking = $this->createBooking();
        Passport::actingAs($this->admin);
        $this->confirm($booking);
        $this->getJson("/api/v1/admin/toyota-service/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonMissing(['action' => 'mark_no_show']);

        $payload = [
            'action' => 'mark_no_show',
            'reason_code' => 'customer_no_show',
            'reason' => 'Pelanggan tidak hadir sampai akhir rentang jadwal.',
        ];
        $this->postJson("/api/v1/admin/toyota-service/bookings/{$booking->id}/actions", $payload)
            ->assertConflict()
            ->assertJsonPath('code', 'TOYOTA_SERVICE_STATE_CONFLICT');

        Carbon::setTestNow(Carbon::parse('2026-07-29 04:01:00', 'UTC'));
        $this->getJson("/api/v1/admin/toyota-service/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonFragment(['action' => 'mark_no_show']);
        $this->postJson("/api/v1/admin/toyota-service/bookings/{$booking->id}/actions", $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'no_show');
    }

    public function test_admin_action_lazily_reconciles_an_expired_alternative(): void
    {
        Passport::actingAs($this->customer);
        $booking = $this->createBooking();
        $booking->update([
            'status' => 'alternative_proposed',
            'proposed_start_at' => Carbon::parse('2026-07-27 00:00:00', 'UTC'),
            'proposed_end_at' => Carbon::parse('2026-07-27 00:30:00', 'UTC'),
            'proposal_context' => 'initial',
            'proposal_reason' => 'Fixture kedaluwarsa.',
            'proposal_expires_at' => Carbon::parse('2026-07-26 23:00:00', 'UTC'),
        ]);
        Passport::actingAs($this->admin);

        $this->postJson("/api/v1/admin/toyota-service/bookings/{$booking->id}/actions", [
            'action' => 'assign',
            'advisor_id' => $this->admin->getKey(),
        ])
            ->assertConflict()
            ->assertJsonPath('code', 'TOYOTA_SERVICE_ALTERNATIVE_EXPIRED');
        $this->assertDatabaseHas('toyota_service_bookings', [
            'id' => $booking->id,
            'status' => 'expired',
            'assigned_service_advisor_id' => null,
        ]);
    }

    public function test_old_confirmed_appointment_can_check_in_while_reschedule_is_pending(): void
    {
        Passport::actingAs($this->customer);
        $booking = $this->createBooking();
        Passport::actingAs($this->admin);
        $this->confirm($booking);

        Passport::actingAs($this->customer);
        $this->postJson("/api/v1/toyota-service/bookings/{$booking->id}/reschedule", [
            'primary_slot' => ['date' => '2026-08-06', 'time_window' => '09:00-11:00'],
            'alternative_slot' => ['date' => '2026-08-05', 'time_window' => '13:00-15:00'],
            'reason' => 'Mencoba jadwal lain, tetapi jadwal lama masih berlaku.',
        ])->assertOk();

        Passport::actingAs($this->admin);
        $this->postJson("/api/v1/admin/toyota-service/bookings/{$booking->id}/actions", [
            'action' => 'propose_alternative',
            'proposed_slot' => ['date' => '2026-08-07', 'time_window' => '09:00-11:00'],
            'proposal_reason' => 'Usulan alternatif untuk jadwal ulang.',
            'proposal_expires_at' => '2026-07-29T01:00:00Z',
            'pic_name' => 'PIC Usulan',
            'arrival_instructions' => 'Instruksi usulan baru.',
        ])->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-07-29 01:30:00', 'UTC'));
        $this->getJson("/api/v1/admin/toyota-service/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonFragment(['action' => 'check_in'])
            ->assertJsonMissing(['action' => 'mark_no_show']);
        $this->postJson("/api/v1/admin/toyota-service/bookings/{$booking->id}/actions", [
            'action' => 'check_in',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'checked_in')
            ->assertJsonPath('data.confirmed_slot.date', '2026-07-29')
            ->assertJsonPath('data.reschedule_request', null)
            ->assertJsonPath('data.external_booking_number', 'A2K-TEST-001');
    }

    private function confirm(ToyotaServiceBooking $booking): void
    {
        $this->postJson("/api/v1/admin/toyota-service/bookings/{$booking->id}/actions", [
            'action' => 'confirm',
            'confirmed_slot' => ['date' => '2026-07-29', 'time_window' => '09:00-11:00'],
            'pic_name' => 'Service Advisor Kertajaya',
            'arrival_instructions' => 'Datang 15 menit sebelum jadwal.',
            'external_booking_number' => 'A2K-TEST-001',
        ])->assertOk();
    }

    private function createBooking(): ToyotaServiceBooking
    {
        $response = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/toyota-service/bookings', [
                'vehicle_id' => $this->vehicle->getKey(),
                'service_location_id' => $this->location->getKey(),
                'service_type_id' => $this->serviceType->getKey(),
                'fulfillment_type' => 'workshop',
                'current_mileage' => 43000,
                'complaint' => 'Rem terasa sedikit bergetar saat kecepatan tinggi.',
                'primary_slot' => ['date' => '2026-07-29', 'time_window' => '09:00-11:00'],
                'alternative_slot' => ['date' => '2026-07-30', 'time_window' => '13:00-15:00'],
                'contact_channel' => 'whatsapp',
                'service_consent' => true,
            ])
            ->assertCreated();

        return ToyotaServiceBooking::query()->findOrFail($response->json('data.id'));
    }
}
