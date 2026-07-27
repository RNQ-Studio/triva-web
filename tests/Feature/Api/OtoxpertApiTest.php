<?php

namespace Tests\Feature\Api;

use App\Models\Asset;
use App\Models\OtoxpertBooking;
use App\Models\OtoxpertService;
use App\Models\OtoxpertWorkshop;
use App\Models\OtoxpertWorkshopServicePrice;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Enums\AssetStatus;
use App\Support\Enums\OtoxpertBookingStatus;
use Database\Seeders\OtoxpertMasterSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

class OtoxpertApiTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Vehicle $vehicle;

    private OtoxpertWorkshop $workshop;

    private OtoxpertService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-27 01:00:00', 'UTC'));
        Queue::fake();
        $this->seed([
            RolePermissionSeeder::class,
            OtoxpertMasterSeeder::class,
        ]);
        $this->customer = User::factory()->create([
            'phone' => '+6281234567890',
            'city' => 'Surabaya',
            'service_consent_at' => now(),
        ]);
        $this->vehicle = Vehicle::factory()->create([
            'user_id' => $this->customer->getKey(),
            'make' => 'Honda',
            'model' => 'Brio',
            'license_plate' => 'L 1234 OX',
        ]);
        $this->workshop = OtoxpertWorkshop::query()
            ->where('code', 'otoxpert-rungkut')
            ->firstOrFail();
        $this->service = OtoxpertService::query()
            ->where('code', 'ganti-oli')
            ->firstOrFail();
    }

    public function test_official_master_seeder_is_idempotent_and_never_seeds_zero_prices(): void
    {
        $workshopCodes = [
            'otoxpert-dukuh-kupang',
            'otoxpert-rungkut',
        ];
        $serviceCodes = [
            'ganti-oli',
            'aki',
            'servis-ringan',
            'servis-lengkap',
            'tune-up',
            'rem',
            'ban',
            'shock-absorber',
            'keluhan-lainnya',
        ];
        $before = [
            'workshops' => OtoxpertWorkshop::query()
                ->whereIn('code', $workshopCodes)
                ->count(),
            'services' => OtoxpertService::query()
                ->whereIn('code', $serviceCodes)
                ->count(),
            'prices' => OtoxpertWorkshopServicePrice::query()
                ->whereHas(
                    'workshop',
                    fn ($query) => $query->whereIn('code', $workshopCodes),
                )
                ->count(),
        ];

        $this->seed(OtoxpertMasterSeeder::class);

        $this->assertSame(2, $before['workshops']);
        $this->assertSame(9, $before['services']);
        $this->assertSame(10, $before['prices']);
        $this->assertSame(
            $before,
            [
                'workshops' => OtoxpertWorkshop::query()
                    ->whereIn('code', $workshopCodes)
                    ->count(),
                'services' => OtoxpertService::query()
                    ->whereIn('code', $serviceCodes)
                    ->count(),
                'prices' => OtoxpertWorkshopServicePrice::query()
                    ->whereHas(
                        'workshop',
                        fn ($query) => $query
                            ->whereIn('code', $workshopCodes),
                    )
                    ->count(),
            ],
        );
        $this->assertFalse(
            OtoxpertWorkshopServicePrice::query()
                ->where('minimum_amount', '<=', 0)
                ->exists(),
        );
        $this->assertFalse(
            OtoxpertWorkshopServicePrice::query()
                ->whereNull('source_url')
                ->exists(),
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_master_and_booking_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/otoxpert/options')->assertUnauthorized();
        $this->getJson('/api/v1/otoxpert/workshops')->assertUnauthorized();
        $this->getJson('/api/v1/otoxpert/availability')->assertUnauthorized();
        $this->getJson('/api/v1/otoxpert/bookings')->assertUnauthorized();
        $this->postJson('/api/v1/otoxpert/bookings')->assertUnauthorized();
        $this->getJson('/api/v1/admin/otoxpert/bookings')
            ->assertUnauthorized();
    }

    public function test_options_workshops_services_and_availability_expose_request_to_confirm_contract(): void
    {
        Passport::actingAs($this->customer);

        $this->getJson('/api/v1/otoxpert/options')
            ->assertOk()
            ->assertJsonPath('data.request_to_confirm', true)
            ->assertJsonPath(
                'data.partner_consent_version',
                'otoxpert-data-sharing-v1',
            )
            ->assertJsonPath(
                'data.photo_upload.type',
                'otoxpert-booking-photo',
            )
            ->assertJsonCount(6, 'data.symptom_options');

        $workshops = $this->getJson(
            '/api/v1/otoxpert/workshops?'.http_build_query([
                'vehicle_id' => $this->vehicle->getKey(),
                'service_id' => $this->service->getKey(),
                'city' => 'Surabaya',
            ])
        )->assertOk();
        $workshops
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.code', 'otoxpert-rungkut');

        $this->getJson(
            "/api/v1/otoxpert/workshops/{$this->workshop->getKey()}/services"
        )
            ->assertOk()
            ->assertJsonPath('data.0.code', 'ganti-oli')
            ->assertJsonPath('data.0.indicative_price.type', 'from')
            ->assertJsonPath(
                'data.0.indicative_price.minimum_amount',
                299000,
            )
            ->assertJsonPath(
                'data.0.indicative_price.source',
                'official_otoxpert_publication',
            );

        $this->getJson('/api/v1/otoxpert/availability?'.http_build_query([
            'workshop_id' => $this->workshop->getKey(),
            'service_id' => $this->service->getKey(),
            'from_date' => '2026-07-28',
            'days' => 2,
        ]))
            ->assertOk()
            ->assertJsonPath('data.is_real_time', false)
            ->assertJsonPath('data.request_to_confirm', true)
            ->assertJsonPath('data.dates.0.date', '2026-07-28')
            ->assertJsonPath('data.dates.0.time_windows.0', '08:00-10:00');
    }

    public function test_workshop_filter_and_booking_are_protected_from_vehicle_idor(): void
    {
        $otherVehicle = Vehicle::factory()->create();
        Passport::actingAs($this->customer);

        $this->getJson('/api/v1/otoxpert/workshops?'.http_build_query([
            'vehicle_id' => $otherVehicle->getKey(),
        ]))->assertForbidden();

        $payload = $this->validPayload();
        $payload['vehicle_id'] = $otherVehicle->getKey();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/otoxpert/bookings', $payload)
            ->assertForbidden();

        $this->workshop->update(['supports_all_vehicle_makes' => false]);
        $this->getJson('/api/v1/otoxpert/workshops?'.http_build_query([
            'vehicle_id' => $this->vehicle->getKey(),
        ]))->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_store_validates_consent_compatibility_and_operational_inputs(): void
    {
        Passport::actingAs($this->customer);
        $invalid = $this->validPayload();
        $invalid['partner_consent'] = false;
        $invalid['complaint'] = 'x';
        $invalid['symptom_codes'] = ['unknown'];
        $invalid['primary_slot'] = [
            'date' => '2026-07-27',
            'time_window' => '08:00-10:00',
        ];
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/otoxpert/bookings', $invalid)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'partner_consent',
                'complaint',
                'symptom_codes.0',
            ]);

        $pickup = $this->validPayload();
        $pickup['pickup_delivery_requested'] = true;
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/otoxpert/bookings', $pickup)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pickup_delivery_requested']);
    }

    public function test_store_is_idempotent_rejects_duplicate_and_snapshots_indicative_price(): void
    {
        Passport::actingAs($this->customer);
        $key = (string) Str::uuid();
        $payload = $this->validPayload();

        $created = $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/otoxpert/bookings', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'awaiting_confirmation')
            ->assertJsonPath('data.is_confirmed', false)
            ->assertJsonPath('data.price.minimum_amount', 299000)
            ->assertJsonPath('data.price.is_final', false)
            ->assertJsonPath('meta.idempotent_replay', false);

        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/otoxpert/bookings', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $created->json('data.id'))
            ->assertJsonPath('meta.idempotent_replay', true);
        $this->assertDatabaseCount('otoxpert_bookings', 1);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/otoxpert/bookings', $payload)
            ->assertConflict()
            ->assertJsonPath('code', 'OTOXPERT_DUPLICATE_ACTIVE');
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->customer->getKey(),
            'type' => 'otoxpert_booking',
        ]);
    }

    public function test_optional_protected_photos_attach_and_foreign_category_is_rejected(): void
    {
        $validAsset = Asset::factory()->create([
            'user_id' => $this->customer->getKey(),
            'category' => 'otoxpert-booking-photo',
            'is_protected' => true,
            'status' => AssetStatus::Active,
        ]);
        $wrongAsset = Asset::factory()->create([
            'user_id' => $this->customer->getKey(),
            'category' => 'toyota-service-photo',
            'is_protected' => true,
            'status' => AssetStatus::Active,
        ]);
        Passport::actingAs($this->customer);

        $invalid = $this->validPayload();
        $invalid['photo_asset_ids'] = [$wrongAsset->getKey()];
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/otoxpert/bookings', $invalid)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo_asset_ids.0']);

        $valid = $this->validPayload();
        $valid['photo_asset_ids'] = [$validAsset->getKey()];
        $response = $this->withHeader(
            'Idempotency-Key',
            (string) Str::uuid(),
        )->postJson('/api/v1/otoxpert/bookings', $valid)->assertCreated();
        $this->assertDatabaseHas('otoxpert_booking_photos', [
            'booking_id' => $response->json('data.id'),
            'asset_id' => $validAsset->getKey(),
        ]);
    }

    public function test_customer_can_only_read_and_mutate_own_booking_lifecycle(): void
    {
        Passport::actingAs($this->customer);
        $created = $this->withHeader(
            'Idempotency-Key',
            (string) Str::uuid(),
        )->postJson(
            '/api/v1/otoxpert/bookings',
            $this->validPayload(),
        )->assertCreated();
        $booking = OtoxpertBooking::query()->findOrFail(
            $created->json('data.id')
        );
        $other = User::factory()->create(['phone' => '+6281234567800']);

        Passport::actingAs($other);
        $this->getJson("/api/v1/otoxpert/bookings/{$booking->getKey()}")
            ->assertForbidden();
        $this->postJson(
            "/api/v1/otoxpert/bookings/{$booking->getKey()}/cancel",
            ['reason' => 'Bukan booking saya.'],
        )->assertForbidden();

        $booking->update([
            'status' => OtoxpertBookingStatus::AlternativeProposed,
            'proposed_start_at' => Carbon::parse('2026-07-30 01:00:00', 'UTC'),
            'proposed_end_at' => Carbon::parse('2026-07-30 03:00:00', 'UTC'),
            'proposal_context' => 'initial',
            'proposal_reason' => 'Jadwal pertama penuh.',
            'proposal_expires_at' => now()->addDay(),
        ]);
        Passport::actingAs($this->customer);
        $this->postJson(
            "/api/v1/otoxpert/bookings/{$booking->getKey()}/accept-alternative"
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.confirmed_slot.date', '2026-07-30');
        $this->postJson(
            "/api/v1/otoxpert/bookings/{$booking->getKey()}/reschedule",
            [
                'primary_slot' => [
                    'date' => '2026-08-03',
                    'time_window' => '08:00-10:00',
                ],
                'alternative_slot' => [
                    'date' => '2026-08-04',
                    'time_window' => '10:00-12:00',
                ],
                'reason' => 'Ada perubahan agenda keluarga.',
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'reschedule_requested')
            ->assertJsonPath('data.confirmed_slot.date', '2026-07-30');
        $this->postJson(
            "/api/v1/otoxpert/bookings/{$booking->getKey()}/cancel",
            ['reason' => 'Rencana servis berubah.'],
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_operator_scope_admin_actions_and_expiry_are_enforced(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('staff');
        $this->workshop->operators()->attach($operator, ['is_active' => true]);
        $ownBooking = $this->createBooking();
        $otherWorkshop = OtoxpertWorkshop::factory()->create();
        $otherService = OtoxpertService::factory()->create();
        $otherWorkshop->services()->attach($otherService, [
            'lead_time_days' => 1,
            'is_active' => true,
        ]);
        $otherBooking = OtoxpertBooking::factory()->create([
            'workshop_id' => $otherWorkshop->getKey(),
            'service_id' => $otherService->getKey(),
        ]);

        Passport::actingAs($operator);
        $this->getJson('/api/v1/admin/otoxpert/bookings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownBooking->getKey());
        $this->getJson(
            "/api/v1/admin/otoxpert/bookings/{$otherBooking->getKey()}"
        )->assertForbidden();

        $this->postJson(
            "/api/v1/admin/otoxpert/bookings/{$ownBooking->getKey()}/actions",
            [
                'action' => 'confirm',
                'slot' => [
                    'date' => '2026-07-28',
                    'time_window' => '08:00-10:00',
                ],
                'pic_name' => 'Budi',
                'arrival_instructions' => 'Datang 15 menit lebih awal.',
                'external_booking_number' => 'OX-RK-1001',
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.external_booking_number', 'OX-RK-1001');

        $expiring = OtoxpertBooking::factory()->create([
            'user_id' => $this->customer->getKey(),
            'vehicle_id' => $this->vehicle->getKey(),
            'workshop_id' => $this->workshop->getKey(),
            'service_id' => $this->service->getKey(),
            'primary_start_at' => Carbon::parse('2026-08-05 01:00:00', 'UTC'),
            'primary_end_at' => Carbon::parse('2026-08-05 03:00:00', 'UTC'),
            'alternative_start_at' => Carbon::parse(
                '2026-08-06 03:00:00',
                'UTC',
            ),
            'alternative_end_at' => Carbon::parse(
                '2026-08-06 05:00:00',
                'UTC',
            ),
        ]);
        $expiring->update([
            'status' => OtoxpertBookingStatus::AlternativeProposed,
            'proposed_start_at' => now()->addDays(2),
            'proposed_end_at' => now()->addDays(2)->addHours(2),
            'proposal_context' => 'initial',
            'proposal_reason' => 'Kapasitas penuh.',
            'proposal_expires_at' => now()->subMinute(),
        ]);
        $this->artisan('otoxpert:expire-alternatives')
            ->expectsOutput('Reconciled 1 OtoXpert booking(s).')
            ->assertExitCode(0);
        $this->assertSame(
            OtoxpertBookingStatus::Expired,
            $expiring->refresh()->status,
        );
        $this->assertDatabaseHas('otoxpert_booking_status_histories', [
            'booking_id' => $expiring->getKey(),
            'event' => 'alternative_expired',
        ]);
    }

    private function createBooking(): OtoxpertBooking
    {
        return OtoxpertBooking::factory()->create([
            'user_id' => $this->customer->getKey(),
            'vehicle_id' => $this->vehicle->getKey(),
            'workshop_id' => $this->workshop->getKey(),
            'service_id' => $this->service->getKey(),
            'primary_start_at' => Carbon::parse('2026-07-28 01:00:00', 'UTC'),
            'primary_end_at' => Carbon::parse('2026-07-28 03:00:00', 'UTC'),
            'alternative_start_at' => Carbon::parse(
                '2026-07-29 03:00:00',
                'UTC',
            ),
            'alternative_end_at' => Carbon::parse(
                '2026-07-29 05:00:00',
                'UTC',
            ),
        ]);
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'vehicle_id' => $this->vehicle->getKey(),
            'workshop_id' => $this->workshop->getKey(),
            'service_id' => $this->service->getKey(),
            'current_mileage' => 42000,
            'last_service_date' => '2026-04-20',
            'complaint' => 'Mesin terdengar kasar dan perlu ganti oli.',
            'symptom_codes' => ['noise'],
            'primary_slot' => [
                'date' => '2026-07-28',
                'time_window' => '08:00-10:00',
            ],
            'alternative_slot' => [
                'date' => '2026-07-29',
                'time_window' => '10:00-12:00',
            ],
            'pickup_delivery_requested' => false,
            'contact_channel' => 'whatsapp',
            'photo_asset_ids' => [],
            'partner_consent' => true,
            'partner_consent_version' => 'otoxpert-data-sharing-v1',
            'campaign_source' => 'triva_home',
        ];
    }
}
