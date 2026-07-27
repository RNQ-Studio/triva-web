<?php

namespace Tests\Feature\Api;

use App\Models\Appraisal;
use App\Models\Asset;
use App\Models\Notification;
use App\Models\ToyotaServiceBooking;
use App\Models\ToyotaServiceHoliday;
use App\Models\ToyotaServiceLocation;
use App\Models\ToyotaServiceType;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Enums\ToyotaServiceBookingStatus;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ToyotaServiceMasterSeeder;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Passport;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ToyotaServiceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

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

    public function test_options_and_availability_require_auth_and_expose_request_to_confirm_master(): void
    {
        $this->getJson('/api/v1/toyota-service/options')->assertUnauthorized();
        $this->getJson('/api/v1/toyota-service/availability')->assertUnauthorized();
        $this->postJson('/api/v1/toyota-service/bookings', [])->assertUnauthorized();

        Passport::actingAs($this->customer);

        $this->getJson('/api/v1/toyota-service/options')
            ->assertOk()
            ->assertJsonPath('data.request_to_confirm', true)
            ->assertJsonPath('data.locations.0.code', 'auto2000-kertajaya')
            ->assertJsonPath('data.locations.0.phone', '0315952000')
            ->assertJsonPath('data.photo_upload.type', 'toyota-service-photo')
            ->assertJsonCount(4, 'data.service_types')
            ->assertJsonPath('data.fulfillment_types.1.is_available', false)
            ->assertJsonPath(
                'data.fulfillment_types.1.unavailable_reason',
                'Cakupan operasional Toyota Home Service belum tersedia.',
            )
            ->assertJsonMissing(['code' => 'toyota-home-service']);

        $this->getJson('/api/v1/toyota-service/availability?'.http_build_query([
            'service_location_id' => $this->location->getKey(),
            'service_type_id' => $this->serviceType->getKey(),
            'fulfillment_type' => 'ths',
            'from_date' => '2026-07-29',
            'days' => 2,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fulfillment_type']);

        $this->getJson('/api/v1/toyota-service/availability?'.http_build_query([
            'service_location_id' => $this->location->getKey(),
            'service_type_id' => $this->serviceType->getKey(),
            'fulfillment_type' => 'workshop',
            'from_date' => '2026-07-29',
            'days' => 2,
        ]))
            ->assertOk()
            ->assertJsonPath('data.is_real_time', false)
            ->assertJsonPath('data.lead_time_days', 2)
            ->assertJsonPath('data.dates.0.date', '2026-07-29')
            ->assertJsonPath('data.dates.0.is_requestable', true)
            ->assertJsonPath('data.dates.0.time_windows.0', '07:00-09:00');
    }

    public function test_availability_marks_lead_time_and_holiday_as_not_requestable(): void
    {
        ToyotaServiceHoliday::query()->create([
            'service_location_id' => $this->location->getKey(),
            'holiday_date' => '2026-07-29',
            'name' => 'Tutup operasional',
            'is_closed' => true,
        ]);
        Passport::actingAs($this->customer);

        $response = $this->getJson('/api/v1/toyota-service/availability?'.http_build_query([
            'service_location_id' => $this->location->getKey(),
            'service_type_id' => $this->serviceType->getKey(),
            'fulfillment_type' => 'workshop',
            'from_date' => '2026-07-28',
            'days' => 2,
        ]))->assertOk();

        $response
            ->assertJsonPath('data.dates.0.is_requestable', false)
            ->assertJsonPath('data.dates.0.reason', 'Minimum lead time H-2.')
            ->assertJsonPath('data.dates.1.is_requestable', false)
            ->assertJsonPath('data.dates.1.reason', 'Tidak beroperasi pada tanggal ini.');
    }

    public function test_zero_lead_time_availability_never_advertises_started_windows(): void
    {
        $this->serviceType->update(['workshop_lead_time_days' => 0]);
        Carbon::setTestNow(Carbon::parse('2026-07-27 01:30:00', 'UTC'));
        Passport::actingAs($this->customer);
        $query = http_build_query([
            'service_location_id' => $this->location->getKey(),
            'service_type_id' => $this->serviceType->getKey(),
            'fulfillment_type' => 'workshop',
            'from_date' => '2026-07-27',
            'days' => 1,
        ]);

        $this->getJson('/api/v1/toyota-service/availability?'.$query)
            ->assertOk()
            ->assertJsonPath('data.dates.0.is_requestable', true)
            ->assertJsonPath('data.dates.0.time_windows.0', '09:00-11:00')
            ->assertJsonMissing(['07:00-09:00']);

        Carbon::setTestNow(Carbon::parse('2026-07-27 08:30:00', 'UTC'));
        $this->getJson('/api/v1/toyota-service/availability?'.$query)
            ->assertOk()
            ->assertJsonPath('data.dates.0.is_requestable', false)
            ->assertJsonPath(
                'data.dates.0.reason',
                'Tidak ada rentang waktu yang tersisa pada tanggal ini.',
            );
    }

    public function test_ths_global_availability_requires_coverage_on_a_ths_capable_location(): void
    {
        $this->location->update(['supports_ths' => false]);
        $workshopOnly = ToyotaServiceLocation::factory()->create([
            'supports_ths' => false,
            'effective_from' => '2026-07-27',
        ]);
        $workshopOnly->thsCoverages()->create([
            'city' => 'Surabaya',
            'latitude_min' => -7.40,
            'latitude_max' => -7.10,
            'longitude_min' => 112.60,
            'longitude_max' => 112.90,
            'is_active' => true,
            'effective_from' => '2026-07-27',
            'verification_source' => 'test_boundary',
        ]);
        Passport::actingAs($this->customer);

        $this->getJson('/api/v1/toyota-service/options')
            ->assertOk()
            ->assertJsonPath('data.fulfillment_types.1.is_available', false)
            ->assertJsonCount(0, 'data.ths_coverage');
    }

    public function test_workshop_global_availability_reflects_effective_master_capability(): void
    {
        $this->location->update(['supports_workshop' => false]);
        Passport::actingAs($this->customer);

        $this->getJson('/api/v1/toyota-service/options')
            ->assertOk()
            ->assertJsonPath('data.fulfillment_types.0.is_available', false)
            ->assertJsonPath(
                'data.fulfillment_types.0.unavailable_reason',
                'Layanan bengkel belum tersedia.',
            );
    }

    public function test_location_model_rejects_invalid_iana_timezone(): void
    {
        try {
            $this->location->update(['timezone' => 'Mars/Olympus_Mons']);
            $this->fail('Invalid timezone should not be persisted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('timezone', $exception->errors());
        }

        $this->location->refresh();
        $this->assertSame('Asia/Jakarta', $this->location->timezone);
        $this->location->update(['timezone' => 'Asia/Jakarta']);
        $this->assertSame('Asia/Jakarta', $this->location->refresh()->timezone);
    }

    public function test_create_validates_missing_payload_then_is_idempotent_and_blocks_duplicate_active_booking(): void
    {
        Passport::actingAs($this->customer);
        $this->postJson('/api/v1/toyota-service/bookings', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'idempotency_key',
                'vehicle_id',
                'service_location_id',
                'service_type_id',
                'fulfillment_type',
            ]);

        $key = (string) Str::uuid();
        $created = $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/toyota-service/bookings', $this->bookingPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'awaiting_confirmation')
            ->assertJsonPath('data.is_confirmed', false)
            ->assertJsonPath('data.requested_slots.primary.date', '2026-07-29')
            ->assertJsonPath('data.service_location.phone', '0315952000')
            ->assertJsonPath('data.service_advisor', null)
            ->assertJsonPath('data.allowed_customer_actions.0', 'cancel')
            ->assertJsonPath('meta.idempotent_replay', false)
            ->assertJsonCount(3, 'data.benefit_checks')
            ->assertJsonCount(1, 'data.timeline');

        $bookingId = $created->json('data.id');
        $this->assertDatabaseHas('toyota_service_bookings', [
            'id' => $bookingId,
            'status' => 'awaiting_confirmation',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->customer->getKey(),
            'type' => 'toyota_service_booking',
        ]);
        $activityProperties = DB::table('activity_log')
            ->where('subject_type', ToyotaServiceBooking::class)
            ->where('subject_id', $bookingId)
            ->pluck('properties')
            ->implode(' ');
        $this->assertStringContainsString('awaiting_confirmation', $activityProperties);
        $this->assertStringNotContainsString(
            'Rem terasa sedikit bergetar',
            $activityProperties,
        );
        $this->assertStringNotContainsString('ths_address', $activityProperties);
        $this->assertStringNotContainsString('campaign_metadata', $activityProperties);

        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/toyota-service/bookings', $this->bookingPayload())
            ->assertOk()
            ->assertJsonPath('data.id', $bookingId)
            ->assertJsonPath('meta.idempotent_replay', true);

        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/toyota-service/bookings', $this->bookingPayload([
                'complaint' => 'Payload berbeda tidak boleh dianggap sebagai replay.',
            ]))
            ->assertConflict()
            ->assertJsonPath('code', 'TOYOTA_SERVICE_IDEMPOTENCY_CONFLICT');

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/toyota-service/bookings', $this->bookingPayload())
            ->assertConflict()
            ->assertJsonPath('code', 'TOYOTA_SERVICE_DUPLICATE_ACTIVE');

        $this->assertDatabaseCount('toyota_service_bookings', 1);
    }

    public function test_create_accepts_an_earlier_alternative_but_rejects_identical_preferences(): void
    {
        Passport::actingAs($this->customer);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/toyota-service/bookings', $this->bookingPayload([
                'primary_slot' => [
                    'date' => '2026-07-30',
                    'time_window' => '13:00-15:00',
                ],
                'alternative_slot' => [
                    'date' => '2026-07-29',
                    'time_window' => '09:00-11:00',
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.requested_slots.primary.date', '2026-07-30')
            ->assertJsonPath('data.requested_slots.alternative.date', '2026-07-29');

        $secondVehicle = Vehicle::factory()->create([
            'user_id' => $this->customer->getKey(),
            'make' => 'Toyota',
        ]);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/toyota-service/bookings', $this->bookingPayload([
                'vehicle_id' => $secondVehicle->getKey(),
                'primary_slot' => [
                    'date' => '2026-07-30',
                    'time_window' => '13:00-15:00',
                ],
                'alternative_slot' => [
                    'date' => '2026-07-30',
                    'time_window' => '13:00-15:00',
                ],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['alternative_slot']);
    }

    public function test_notification_persistence_failure_rolls_back_booking_creation(): void
    {
        Notification::creating(function (): never {
            throw new RuntimeException('Simulated notification persistence failure.');
        });
        Passport::actingAs($this->customer);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/toyota-service/bookings', $this->bookingPayload())
            ->assertServerError();

        $this->assertDatabaseCount('toyota_service_bookings', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_push_dispatch_failure_does_not_turn_committed_booking_into_api_failure(): void
    {
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->andThrow(new RuntimeException('Queue transport unavailable.'));
        $this->app->instance(Dispatcher::class, $dispatcher);
        Passport::actingAs($this->customer);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/toyota-service/bookings', $this->bookingPayload())
            ->assertCreated();

        $this->assertDatabaseCount('toyota_service_bookings', 1);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertNotNull(Notification::query()->firstOrFail()->failed_at);
    }

    public function test_booking_rejects_non_toyota_invalid_ths_and_missing_phone(): void
    {
        Passport::actingAs($this->customer);
        $nonToyota = Vehicle::factory()->create([
            'user_id' => $this->customer->getKey(),
            'make' => 'Honda',
        ]);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/toyota-service/bookings', $this->bookingPayload([
                'vehicle_id' => $nonToyota->getKey(),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vehicle_id']);

        $foreignVehicle = Vehicle::factory()->create([
            'user_id' => User::factory(),
            'make' => 'Toyota',
        ]);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/toyota-service/bookings', $this->bookingPayload([
                'vehicle_id' => $foreignVehicle->getKey(),
            ]))
            ->assertForbidden();

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/toyota-service/bookings', $this->bookingPayload([
                'fulfillment_type' => 'ths',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'ths_address',
                'ths_city',
                'ths_latitude',
                'ths_longitude',
            ]);

        $this->customer->update(['phone' => null]);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/toyota-service/bookings', $this->bookingPayload([
                'contact_channel' => 'email',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_ths_submit_requires_active_coverage_but_availability_does_not_require_address(): void
    {
        Passport::actingAs($this->customer);
        $this->location->thsCoverages()->update([
            'is_active' => true,
            'latitude_min' => -7.40,
            'latitude_max' => -7.10,
            'longitude_min' => 112.60,
            'longitude_max' => 112.90,
            'verification_source' => 'test_operational_boundary',
        ]);

        $this->getJson('/api/v1/toyota-service/options')
            ->assertOk()
            ->assertJsonPath('data.fulfillment_types.1.is_available', true)
            ->assertJsonPath('data.fulfillment_types.1.unavailable_reason', null);

        $this->getJson('/api/v1/toyota-service/availability?'.http_build_query([
            'service_location_id' => $this->location->getKey(),
            'service_type_id' => $this->serviceType->getKey(),
            'fulfillment_type' => 'ths',
            'from_date' => '2026-07-28',
            'days' => 1,
        ]))
            ->assertOk()
            ->assertJsonPath('data.dates.0.is_requestable', true);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/toyota-service/bookings', $this->bookingPayload([
                'fulfillment_type' => 'ths',
                'primary_slot' => ['date' => '2026-07-28', 'time_window' => '07:00-09:00'],
                'alternative_slot' => ['date' => '2026-07-29', 'time_window' => '09:00-11:00'],
                'ths_address' => 'Jl. Contoh No. 10, Surabaya',
                'ths_city' => 'Malang',
                'ths_latitude' => -7.98,
                'ths_longitude' => 112.63,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ths_city']);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/toyota-service/bookings', $this->bookingPayload([
                'fulfillment_type' => 'ths',
                'primary_slot' => ['date' => '2026-07-28', 'time_window' => '07:00-09:00'],
                'alternative_slot' => ['date' => '2026-07-29', 'time_window' => '09:00-11:00'],
                'ths_address' => 'Alamat Surabaya di luar batas operasional.',
                'ths_city' => 'Surabaya',
                'ths_latitude' => -7.80,
                'ths_longitude' => 112.74,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ths_city']);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/toyota-service/bookings', $this->bookingPayload([
                'fulfillment_type' => 'ths',
                'primary_slot' => ['date' => '2026-07-28', 'time_window' => '07:00-09:00'],
                'alternative_slot' => ['date' => '2026-07-29', 'time_window' => '09:00-11:00'],
                'ths_address' => 'Jl. Contoh No. 10, Surabaya',
                'ths_city' => 'Surabaya',
                'ths_latitude' => -7.28,
                'ths_longitude' => 112.74,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.fulfillment_type', 'ths')
            ->assertJsonPath('data.ths_city', 'Surabaya')
            ->assertJsonPath('data.ths_latitude', -7.28);
    }

    public function test_business_date_drives_effective_master_and_reference_near_utc_boundary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-26 17:30:00', 'UTC'));
        Passport::actingAs($this->customer);

        $this->getJson('/api/v1/toyota-service/options')
            ->assertOk()
            ->assertJsonPath('data.locations.0.code', 'auto2000-kertajaya');

        $created = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/toyota-service/bookings', $this->bookingPayload())
            ->assertCreated();

        $this->assertStringStartsWith('BTS-20260727-', $created->json('data.reference_no'));
    }

    public function test_postgresql_rejects_active_unbounded_ths_coverage(): void
    {
        $coverage = $this->location->thsCoverages()->firstOrFail();
        $coverage->fill([
            'is_active' => true,
            'verification_source' => '',
        ]);

        $this->expectException(QueryException::class);
        $coverage->save();
    }

    public function test_ths_coverage_database_default_is_inactive_and_unbounded_safe(): void
    {
        $coverage = $this->location->thsCoverages()->create([
            'city' => 'Gresik',
            'effective_from' => '2026-07-27',
            'verification_source' => 'unverified_pending_configuration',
        ])->refresh();

        $this->assertFalse($coverage->is_active);
        $this->assertFalse($coverage->containsCoordinates(-7.15, 112.65));
        $this->assertFalse(
            $this->location->thsCoverages()->operational()->whereKey($coverage->getKey())->exists()
        );
    }

    public function test_optional_photo_must_be_owned_protected_booking_image(): void
    {
        $owned = Asset::factory()->create([
            'user_id' => $this->customer->getKey(),
            'category' => 'toyota-service-photo',
            'is_protected' => true,
        ]);
        $foreign = Asset::factory()->create([
            'user_id' => User::factory(),
            'category' => 'toyota-service-photo',
            'is_protected' => true,
        ]);
        Passport::actingAs($this->customer);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/toyota-service/bookings', $this->bookingPayload([
                'photo_asset_ids' => [$foreign->getKey()],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo_asset_ids.0']);

        $response = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/toyota-service/bookings', $this->bookingPayload([
                'photo_asset_ids' => [$owned->getKey()],
                'primary_slot' => ['date' => '2026-07-30', 'time_window' => '09:00-11:00'],
                'alternative_slot' => ['date' => '2026-07-31', 'time_window' => '13:00-15:00'],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.photos.0.asset.id', $owned->getKey());

        $this->assertDatabaseHas('toyota_service_booking_photos', [
            'service_booking_id' => $response->json('data.id'),
            'asset_id' => $owned->getKey(),
        ]);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/toyota-service/bookings', $this->bookingPayload([
                'photo_asset_ids' => [$owned->getKey()],
                'primary_slot' => ['date' => '2026-08-03', 'time_window' => '09:00-11:00'],
                'alternative_slot' => ['date' => '2026-08-04', 'time_window' => '13:00-15:00'],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo_asset_ids']);
    }

    public function test_customer_list_detail_ownership_and_not_found_are_enforced(): void
    {
        Passport::actingAs($this->customer);
        $created = $this->createBooking();
        $bookingId = $created->json('data.id');

        $this->getJson('/api/v1/toyota-service/bookings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.pagination.total', 1);
        $this->getJson("/api/v1/toyota-service/bookings/{$bookingId}")
            ->assertOk()
            ->assertJsonPath('data.id', $bookingId);

        $other = User::factory()->create();
        Passport::actingAs($other);
        $this->getJson('/api/v1/toyota-service/bookings')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/toyota-service/bookings/{$bookingId}")
            ->assertForbidden();
        $this->postJson("/api/v1/toyota-service/bookings/{$bookingId}/cancel", [
            'reason' => 'Tidak jadi melakukan servis.',
        ])->assertForbidden();
        $this->getJson('/api/v1/toyota-service/bookings/'.Str::uuid())
            ->assertNotFound();
    }

    public function test_customer_booking_list_is_stable_across_equal_timestamp_pages(): void
    {
        $ids = [
            '00000000-0000-4000-8000-000000000001',
            '00000000-0000-4000-8000-000000000002',
        ];
        $vehicles = [
            $this->vehicle,
            Vehicle::factory()->create([
                'user_id' => $this->customer->getKey(),
                'license_plate' => 'L 4321 TRV',
            ]),
        ];

        foreach ($ids as $index => $id) {
            ToyotaServiceBooking::factory()->create([
                'id' => $id,
                'user_id' => $this->customer->getKey(),
                'vehicle_id' => $vehicles[$index]->getKey(),
                'service_location_id' => $this->location->getKey(),
                'service_type_id' => $this->serviceType->getKey(),
                'updated_at' => now(),
            ]);
        }

        Passport::actingAs($this->customer);

        $this->getJson('/api/v1/toyota-service/bookings?per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $ids[1]);
        $this->getJson('/api/v1/toyota-service/bookings?per_page=1&page=2')
            ->assertOk()
            ->assertJsonPath('data.0.id', $ids[0]);
    }

    public function test_source_appraisal_must_be_owned_same_vehicle_and_bp_source_is_rejected_until_domain_exists(): void
    {
        $matching = Appraisal::factory()->create([
            'user_id' => $this->customer->getKey(),
            'vehicle_id' => $this->vehicle->getKey(),
        ]);
        $otherVehicle = Vehicle::factory()->create(['user_id' => $this->customer->getKey()]);
        $mismatched = Appraisal::factory()->create([
            'user_id' => $this->customer->getKey(),
            'vehicle_id' => $otherVehicle->getKey(),
        ]);
        Passport::actingAs($this->customer);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/toyota-service/bookings', $this->bookingPayload([
                'source_appraisal_id' => $mismatched->getKey(),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_appraisal_id']);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/toyota-service/bookings', $this->bookingPayload([
                'source_bp_estimate_id' => (string) Str::uuid(),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_bp_estimate_id']);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/toyota-service/bookings', $this->bookingPayload([
                'source_appraisal_id' => $matching->getKey(),
                'primary_slot' => ['date' => '2026-07-30', 'time_window' => '09:00-11:00'],
                'alternative_slot' => ['date' => '2026-07-31', 'time_window' => '13:00-15:00'],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.source.appraisal_id', $matching->getKey());
    }

    public function test_customer_can_accept_or_reject_staff_alternative_with_audited_slots(): void
    {
        Passport::actingAs($this->customer);
        $created = $this->createBooking();
        $bookingId = $created->json('data.id');
        $admin = $this->admin();
        Passport::actingAs($admin);

        $this->postJson("/api/v1/admin/toyota-service/bookings/{$bookingId}/actions", [
            'action' => 'propose_alternative',
            'proposed_slot' => ['date' => '2026-07-31', 'time_window' => '09:00-11:00'],
            'proposal_reason' => 'Kapasitas pada pilihan awal sudah penuh.',
            'proposal_expires_at' => '2026-07-28T01:00:00Z',
            'pic_name' => 'Service Advisor Kertajaya',
            'arrival_instructions' => 'Datang 15 menit lebih awal dan tunjukkan nomor referensi.',
            'external_booking_number' => 'A2K-TEST-001',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'alternative_proposed')
            ->assertJsonPath('data.service_advisor', null)
            ->assertJsonPath('data.proposed_slot.pic_name', 'Service Advisor Kertajaya')
            ->assertJsonPath('data.timeline.1.metadata.proposed_slot.start_at', '2026-07-31T02:00:00+00:00');

        Passport::actingAs($this->customer);
        $this->postJson("/api/v1/toyota-service/bookings/{$bookingId}/accept-alternative")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.confirmed_slot.date', '2026-07-31')
            ->assertJsonPath('data.proposed_slot', null)
            ->assertJsonPath('data.reschedule_request', null)
            ->assertJsonPath('data.service_advisor.name', 'Service Advisor Kertajaya')
            ->assertJsonPath('data.service_advisor.phone', '0315952000')
            ->assertJsonPath('data.external_booking_number', 'A2K-TEST-001');

        $this->postJson("/api/v1/toyota-service/bookings/{$bookingId}/accept-alternative")
            ->assertConflict()
            ->assertJsonPath('code', 'TOYOTA_SERVICE_STATE_CONFLICT');

        $second = $this->createBooking([
            'primary_slot' => ['date' => '2026-08-03', 'time_window' => '09:00-11:00'],
            'alternative_slot' => ['date' => '2026-08-04', 'time_window' => '13:00-15:00'],
        ]);
        $secondId = $second->json('data.id');
        Passport::actingAs($admin);
        $this->postJson("/api/v1/admin/toyota-service/bookings/{$secondId}/actions", [
            'action' => 'propose_alternative',
            'proposed_slot' => ['date' => '2026-08-05', 'time_window' => '09:00-11:00'],
            'proposal_reason' => 'Pilihan awal tidak tersedia.',
            'proposal_expires_at' => '2026-07-29T01:00:00Z',
            'pic_name' => 'Service Advisor Kertajaya',
            'arrival_instructions' => 'Tunjukkan nomor referensi kepada petugas.',
        ])->assertOk();

        Passport::actingAs($this->customer);
        $this->postJson("/api/v1/toyota-service/bookings/{$secondId}/reject-alternative", [
            'primary_slot' => ['date' => '2026-08-07', 'time_window' => '09:00-11:00'],
            'alternative_slot' => ['date' => '2026-08-06', 'time_window' => '13:00-15:00'],
            'reason' => 'Jadwal usulan belum sesuai.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'awaiting_confirmation')
            ->assertJsonPath('data.requested_slots.primary.date', '2026-08-07')
            ->assertJsonPath('data.timeline.2.metadata.rejected_proposed_slot.start_at', '2026-08-05T02:00:00+00:00');
    }

    public function test_customer_cannot_accept_an_alternative_whose_slot_has_started(): void
    {
        Passport::actingAs($this->customer);
        $created = $this->createBooking();
        $booking = ToyotaServiceBooking::query()->findOrFail($created->json('data.id'));
        $booking->update([
            'status' => ToyotaServiceBookingStatus::AlternativeProposed,
            'proposed_start_at' => Carbon::parse('2026-07-27 00:00:00', 'UTC'),
            'proposed_end_at' => Carbon::parse('2026-07-27 00:30:00', 'UTC'),
            'proposal_context' => 'initial',
            'proposal_reason' => 'Fixture proposal yang sudah dimulai.',
            'proposal_expires_at' => Carbon::parse('2026-07-26 23:00:00', 'UTC'),
        ]);

        $this->postJson("/api/v1/toyota-service/bookings/{$booking->id}/accept-alternative")
            ->assertConflict()
            ->assertJsonPath('code', 'TOYOTA_SERVICE_ALTERNATIVE_EXPIRED');
        $this->assertDatabaseHas('toyota_service_bookings', [
            'id' => $booking->id,
            'status' => 'expired',
            'proposed_start_at' => null,
            'proposal_expires_at' => null,
        ]);

        $second = $this->createBooking([
            'primary_slot' => ['date' => '2026-08-03', 'time_window' => '09:00-11:00'],
            'alternative_slot' => ['date' => '2026-08-04', 'time_window' => '13:00-15:00'],
        ]);
        $secondBooking = ToyotaServiceBooking::query()->findOrFail($second->json('data.id'));
        $secondBooking->update([
            'status' => ToyotaServiceBookingStatus::AlternativeProposed,
            'proposed_start_at' => Carbon::parse('2026-07-27 00:00:00', 'UTC'),
            'proposed_end_at' => Carbon::parse('2026-07-27 00:30:00', 'UTC'),
            'proposal_context' => 'initial',
            'proposal_reason' => 'Fixture proposal yang sudah dimulai.',
            'proposal_expires_at' => Carbon::parse('2026-07-26 23:00:00', 'UTC'),
        ]);

        $this->postJson("/api/v1/toyota-service/bookings/{$secondBooking->id}/reject-alternative", [
            'primary_slot' => ['date' => '2026-08-06', 'time_window' => '09:00-11:00'],
            'alternative_slot' => ['date' => '2026-08-05', 'time_window' => '13:00-15:00'],
            'reason' => 'Jadwal usulan sudah lewat.',
        ])
            ->assertConflict()
            ->assertJsonPath('code', 'TOYOTA_SERVICE_ALTERNATIVE_EXPIRED');
        $this->assertDatabaseHas('toyota_service_bookings', [
            'id' => $secondBooking->id,
            'status' => 'expired',
        ]);
    }

    public function test_reschedule_preserves_old_confirmation_until_staff_confirms_requested_slot(): void
    {
        Passport::actingAs($this->customer);
        $created = $this->createBooking([
            'primary_slot' => ['date' => '2026-08-03', 'time_window' => '09:00-11:00'],
            'alternative_slot' => ['date' => '2026-08-04', 'time_window' => '13:00-15:00'],
        ]);
        $bookingId = $created->json('data.id');
        $admin = $this->admin();
        Passport::actingAs($admin);
        $this->confirmBooking($bookingId, '2026-08-03', '09:00-11:00');

        Passport::actingAs($this->customer);
        $this->postJson("/api/v1/toyota-service/bookings/{$bookingId}/reschedule", [
            'primary_slot' => ['date' => '2026-08-06', 'time_window' => '09:00-11:00'],
            'alternative_slot' => ['date' => '2026-08-05', 'time_window' => '13:00-15:00'],
            'reason' => 'Ada agenda mendadak pada jadwal sebelumnya.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'reschedule_requested')
            ->assertJsonPath('data.confirmed_slot.date', '2026-08-03')
            ->assertJsonPath('data.reschedule_request.primary.date', '2026-08-06');

        Passport::actingAs($admin);
        $this->postJson("/api/v1/admin/toyota-service/bookings/{$bookingId}/actions", [
            'action' => 'confirm_reschedule',
            'confirmed_slot' => ['date' => '2026-08-07', 'time_window' => '09:00-11:00'],
            'pic_name' => 'Service Advisor Kertajaya',
            'arrival_instructions' => 'Datang 15 menit lebih awal.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['confirmed_slot']);

        $this->postJson("/api/v1/admin/toyota-service/bookings/{$bookingId}/actions", [
            'action' => 'confirm_reschedule',
            'confirmed_slot' => ['date' => '2026-08-06', 'time_window' => '09:00-11:00'],
            'pic_name' => 'Service Advisor Kertajaya',
            'arrival_instructions' => 'Datang 15 menit lebih awal.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.confirmed_slot.date', '2026-08-06')
            ->assertJsonPath('data.proposed_slot', null)
            ->assertJsonPath('data.reschedule_request', null)
            ->assertJsonPath('data.timeline.2.metadata.previous_confirmed_slot.start_at', '2026-08-03T02:00:00+00:00');
    }

    public function test_reschedule_proposal_replacement_rejection_and_expiry_preserve_old_details(): void
    {
        Passport::actingAs($this->customer);
        $created = $this->createBooking([
            'primary_slot' => ['date' => '2026-08-03', 'time_window' => '09:00-11:00'],
            'alternative_slot' => ['date' => '2026-08-04', 'time_window' => '13:00-15:00'],
        ]);
        $bookingId = $created->json('data.id');
        $admin = $this->admin();
        Passport::actingAs($admin);
        $this->postJson("/api/v1/admin/toyota-service/bookings/{$bookingId}/actions", [
            'action' => 'confirm',
            'confirmed_slot' => ['date' => '2026-08-03', 'time_window' => '09:00-11:00'],
            'pic_name' => 'PIC Lama',
            'arrival_instructions' => 'Instruksi lama tetap berlaku.',
            'external_booking_number' => 'OLD-EXT-001',
        ])->assertOk();

        $proposal = [
            'action' => 'propose_alternative',
            'proposed_slot' => ['date' => '2026-08-07', 'time_window' => '09:00-11:00'],
            'proposal_reason' => 'Jadwal ulang alternatif.',
            'proposal_expires_at' => '2026-07-29T01:00:00Z',
            'pic_name' => 'PIC Usulan Baru',
            'arrival_instructions' => 'Instruksi usulan tidak boleh mengganti jadwal lama.',
        ];
        $this->postJson(
            "/api/v1/admin/toyota-service/bookings/{$bookingId}/actions",
            $proposal,
        )
            ->assertOk()
            ->assertJsonPath('data.proposed_slot.context', 'reschedule')
            ->assertJsonPath('data.proposed_slot.pic_name', 'PIC Usulan Baru')
            ->assertJsonPath('data.arrival_instructions', 'Instruksi lama tetap berlaku.')
            ->assertJsonPath('data.external_booking_number', 'OLD-EXT-001');

        $proposal['proposed_slot'] = [
            'date' => '2026-08-08',
            'time_window' => '09:00-11:00',
        ];
        $proposal['proposal_expires_at'] = '2026-07-29T02:00:00Z';
        $this->postJson(
            "/api/v1/admin/toyota-service/bookings/{$bookingId}/actions",
            $proposal,
        )
            ->assertOk()
            ->assertJsonPath('data.proposed_slot.context', 'reschedule');

        Passport::actingAs($this->customer);
        $this->postJson("/api/v1/toyota-service/bookings/{$bookingId}/reject-alternative", [
            'primary_slot' => ['date' => '2026-08-06', 'time_window' => '09:00-11:00'],
            'alternative_slot' => ['date' => '2026-08-05', 'time_window' => '13:00-15:00'],
            'reason' => 'Tetap ajukan preferensi lain.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'reschedule_requested')
            ->assertJsonPath('data.service_advisor.name', 'PIC Lama')
            ->assertJsonPath('data.arrival_instructions', 'Instruksi lama tetap berlaku.')
            ->assertJsonPath('data.external_booking_number', 'OLD-EXT-001')
            ->assertJsonPath('data.proposed_slot', null);

        Passport::actingAs($admin);
        $proposal['proposed_slot'] = [
            'date' => '2026-08-07',
            'time_window' => '09:00-11:00',
        ];
        $proposal['proposal_expires_at'] = '2026-07-30T01:00:00Z';
        $this->postJson(
            "/api/v1/admin/toyota-service/bookings/{$bookingId}/actions",
            $proposal,
        )->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-07-30 01:01:00', 'UTC'));
        $this->artisan('toyota-service:expire-alternatives')->assertSuccessful();
        Passport::actingAs($this->customer);
        $this->getJson("/api/v1/toyota-service/bookings/{$bookingId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.service_advisor.name', 'PIC Lama')
            ->assertJsonPath('data.arrival_instructions', 'Instruksi lama tetap berlaku.')
            ->assertJsonPath('data.external_booking_number', 'OLD-EXT-001')
            ->assertJsonPath('data.proposed_slot', null);

        Passport::actingAs($admin);
        $proposal['proposal_expires_at'] = '2026-07-31T01:00:00Z';
        $this->postJson(
            "/api/v1/admin/toyota-service/bookings/{$bookingId}/actions",
            $proposal,
        )->assertOk();
        Passport::actingAs($this->customer);
        $this->postJson("/api/v1/toyota-service/bookings/{$bookingId}/accept-alternative")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath(
                'data.arrival_instructions',
                'Instruksi usulan tidak boleh mengganti jadwal lama.',
            )
            ->assertJsonPath('data.external_booking_number', 'OLD-EXT-001');
    }

    public function test_cancel_obeys_cutoff_and_illegal_transition_returns_conflict(): void
    {
        Passport::actingAs($this->customer);
        $created = $this->createBooking();
        $bookingId = $created->json('data.id');

        $this->postJson("/api/v1/toyota-service/bookings/{$bookingId}/cancel", [
            'reason' => 'Rencana servis berubah.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
        $this->postJson("/api/v1/toyota-service/bookings/{$bookingId}/cancel", [
            'reason' => 'Mencoba membatalkan kembali.',
        ])
            ->assertConflict()
            ->assertJsonPath('code', 'TOYOTA_SERVICE_CANCELLATION_NOT_ALLOWED');

        $second = $this->createBooking([
            'primary_slot' => ['date' => '2026-08-03', 'time_window' => '09:00-11:00'],
            'alternative_slot' => ['date' => '2026-08-04', 'time_window' => '13:00-15:00'],
        ]);
        $secondId = $second->json('data.id');
        $admin = $this->admin();
        Passport::actingAs($admin);
        $this->confirmBooking($secondId, '2026-08-03', '09:00-11:00');

        Carbon::setTestNow(Carbon::parse('2026-08-03 00:00:00', 'UTC'));
        Passport::actingAs($this->customer);
        $this->postJson("/api/v1/toyota-service/bookings/{$secondId}/cancel", [
            'reason' => 'Terlambat membatalkan booking.',
        ])
            ->assertConflict()
            ->assertJsonPath('code', 'TOYOTA_SERVICE_CANCELLATION_NOT_ALLOWED');
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['phone' => '0315952000']);
        $admin->assignRole('admin');

        return $admin;
    }

    private function confirmBooking(string $bookingId, string $date, string $window): void
    {
        $this->postJson("/api/v1/admin/toyota-service/bookings/{$bookingId}/actions", [
            'action' => 'confirm',
            'confirmed_slot' => ['date' => $date, 'time_window' => $window],
            'pic_name' => 'Service Advisor Kertajaya',
            'arrival_instructions' => 'Datang 15 menit lebih awal.',
        ])->assertOk();
    }

    private function createBooking(array $overrides = [])
    {
        return $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/toyota-service/bookings', $this->bookingPayload($overrides))
            ->assertCreated();
    }

    /** @return array<string, mixed> */
    private function bookingPayload(array $overrides = []): array
    {
        return [
            'vehicle_id' => $this->vehicle->getKey(),
            'service_location_id' => $this->location->getKey(),
            'service_type_id' => $this->serviceType->getKey(),
            'fulfillment_type' => 'workshop',
            'current_mileage' => 43000,
            'complaint' => 'Rem terasa sedikit bergetar saat kecepatan tinggi.',
            'primary_slot' => [
                'date' => '2026-07-29',
                'time_window' => '09:00-11:00',
            ],
            'alternative_slot' => [
                'date' => '2026-07-30',
                'time_window' => '13:00-15:00',
            ],
            'contact_channel' => 'whatsapp',
            'service_consent' => true,
            ...$overrides,
        ];
    }
}
