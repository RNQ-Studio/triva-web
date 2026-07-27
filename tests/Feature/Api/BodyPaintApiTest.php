<?php

namespace Tests\Feature\Api;

use App\Models\Asset;
use App\Models\BodyPaintPriceItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Enums\AssetStatus;
use App\Support\Enums\StorageType;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ToyotaServiceMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

class BodyPaintApiTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-28 01:00:00', 'UTC'));
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
            'make' => 'Honda',
            'model' => 'Brio',
            'mileage' => 42000,
            'license_plate' => 'L 1234 BP',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_customer_and_admin_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/body-paint/options')->assertUnauthorized();
        $this->getJson('/api/v1/body-paint/estimates')
            ->assertUnauthorized();
        $this->postJson('/api/v1/body-paint/estimates')
            ->assertUnauthorized();
        $this->getJson('/api/v1/admin/body-paint/estimates')
            ->assertUnauthorized();
    }

    public function test_options_expose_complete_catalog_photo_and_booking_contract(): void
    {
        Passport::actingAs($this->customer);

        $this->getJson('/api/v1/body-paint/options')
            ->assertOk()
            ->assertJsonCount(17, 'data.panels')
            ->assertJsonCount(8, 'data.damage_types')
            ->assertJsonCount(4, 'data.severities')
            ->assertJsonCount(5, 'data.work_types')
            ->assertJsonPath(
                'data.photo_upload.type',
                'body-paint-estimate-photo',
            )
            ->assertJsonPath(
                'data.photo_upload.minimum_close_per_damage',
                1,
            )
            ->assertJsonPath('data.photo_upload.minimum_context', 1)
            ->assertJsonPath('data.booking.request_to_confirm', true)
            ->assertJsonPath(
                'data.service_locations.0.code',
                'auto2000-kertajaya',
            )
            ->assertJsonPath('data.requires_physical_inspection', true);
    }

    public function test_draft_is_idempotent_and_protected_from_vehicle_idor(): void
    {
        Passport::actingAs($this->customer);
        $key = (string) Str::uuid();
        $payload = [
            'vehicle_id' => $this->vehicle->getKey(),
            'customer_notes' => 'Baret setelah parkir.',
            'campaign_source' => 'home',
        ];
        $created = $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/body-paint/estimates', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('meta.idempotent_replay', false);

        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/body-paint/estimates', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $created->json('data.id'))
            ->assertJsonPath('meta.idempotent_replay', true);
        $this->assertDatabaseCount('body_paint_estimates', 1);

        $changed = $payload;
        $changed['customer_notes'] = 'Payload berbeda.';
        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/body-paint/estimates', $changed)
            ->assertConflict()
            ->assertJsonPath('code', 'BODY_PAINT_IDEMPOTENCY_CONFLICT');

        $otherVehicle = Vehicle::factory()->create();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/body-paint/estimates', [
                'vehicle_id' => $otherVehicle->getKey(),
            ])
            ->assertForbidden();
    }

    public function test_submit_requires_unique_damage_close_photo_and_context_photo(): void
    {
        Passport::actingAs($this->customer);
        $estimate = $this->createDraft();

        $this->putJson(
            "/api/v1/body-paint/estimates/{$estimate['id']}/damages",
            [
                'damages' => [
                    $this->damagePayload(),
                    $this->damagePayload(),
                ],
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['damages.1.damage_type']);

        $updated = $this->putJson(
            "/api/v1/body-paint/estimates/{$estimate['id']}/damages",
            ['damages' => [$this->damagePayload()]],
        )
            ->assertOk()
            ->assertJsonPath(
                'data.damages.0.panel_code',
                'front_bumper',
            );
        $damageId = $updated->json('data.damages.0.id');

        $this->postJson(
            "/api/v1/body-paint/estimates/{$estimate['id']}/submit",
            $this->submitPayload(),
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photos']);

        $close = $this->photoAsset();
        $this->postJson(
            "/api/v1/body-paint/estimates/{$estimate['id']}/photos",
            [
                'photos' => [[
                    'asset_id' => $close->getKey(),
                    'damage_id' => $damageId,
                    'photo_type' => 'close',
                ]],
            ],
        )->assertOk();

        $this->postJson(
            "/api/v1/body-paint/estimates/{$estimate['id']}/submit",
            $this->submitPayload(),
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photos']);

        $context = $this->photoAsset();
        $this->postJson(
            "/api/v1/body-paint/estimates/{$estimate['id']}/photos",
            [
                'photos' => [[
                    'asset_id' => $context->getKey(),
                    'photo_type' => 'context',
                ]],
            ],
        )->assertOk();

        $this->postJson(
            "/api/v1/body-paint/estimates/{$estimate['id']}/submit",
            $this->submitPayload(),
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'manual_review')
            ->assertJsonPath('data.estimate', null);
    }

    public function test_engine_snapshots_effective_matrix_but_never_auto_publishes(): void
    {
        BodyPaintPriceItem::factory()->create();
        Passport::actingAs($this->customer);
        $estimate = $this->completeAndSubmit();

        $this->assertSame('auto_estimated', $estimate['status']);
        $this->assertNull($estimate['estimate']);
        $this->assertDatabaseHas('body_paint_estimates', [
            'id' => $estimate['id'],
            'engine_total_low' => 750000,
            'engine_total_high' => 1150000,
            'published_total_low' => null,
        ]);
        $this->assertDatabaseHas('body_paint_estimate_items', [
            'estimate_id' => $estimate['id'],
            'matrix_code' => 'BP-TEST',
            'matrix_version' => 1,
            'estimate_version' => null,
            'is_engine_item' => true,
        ]);

        BodyPaintPriceItem::factory()->create([
            'item_code' => 'NEW-VERSION',
            'version' => 2,
            'labor_low' => 900000,
            'labor_high' => 1200000,
        ]);
        $this->getJson("/api/v1/body-paint/estimates/{$estimate['id']}")
            ->assertOk()
            ->assertJsonPath('data.estimate', null);
        $this->assertDatabaseHas('body_paint_estimate_items', [
            'estimate_id' => $estimate['id'],
            'labor_low' => 500000,
            'labor_high' => 750000,
        ]);
    }

    public function test_high_risk_damage_always_requires_manual_review(): void
    {
        BodyPaintPriceItem::factory()->create([
            'damage_type' => 'collision',
            'severity' => 'heavy',
            'is_high_risk' => true,
        ]);
        Passport::actingAs($this->customer);
        $estimate = $this->completeAndSubmit([
            'damage_type' => 'collision',
            'severity' => 'heavy',
        ]);

        $this->assertSame('manual_review', $estimate['status']);
        $this->assertDatabaseHas('body_paint_estimates', [
            'id' => $estimate['id'],
            'has_high_risk_damage' => true,
        ]);
    }

    public function test_estimator_assignment_photo_correction_publish_and_revision_are_audited(): void
    {
        BodyPaintPriceItem::factory()->create();
        Passport::actingAs($this->customer);
        $estimate = $this->completeAndSubmit();
        $photoId = $estimate['damages'][0]['photos'][0]['id'];
        $damageId = $estimate['damages'][0]['id'];

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $estimator = User::factory()->create();
        $estimator->assignRole('staff');

        Passport::actingAs($admin);
        $this->postJson(
            "/api/v1/admin/body-paint/estimates/{$estimate['id']}/actions",
            [
                'action' => 'assign',
                'estimator_id' => $estimator->getKey(),
            ],
        )
            ->assertOk()
            ->assertJsonPath(
                'data.assigned_estimator.id',
                $estimator->getKey(),
            );

        Passport::actingAs($estimator);
        $this->postJson(
            "/api/v1/admin/body-paint/estimates/{$estimate['id']}/actions",
            ['action' => 'start_review'],
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'under_estimator_review');
        $this->postJson(
            "/api/v1/admin/body-paint/estimates/{$estimate['id']}/actions",
            [
                'action' => 'request_photos',
                'reason_code' => 'blurred',
                'reason' => 'Foto bumper terlalu buram. Ambil ulang dengan cahaya cukup.',
                'rejected_photo_ids' => [$photoId],
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'needs_customer_action')
            ->assertJsonPath(
                'data.damages.0.photos.0.review_status',
                'rejected',
            );

        Passport::actingAs($this->customer);
        $replacement = $this->photoAsset();
        $this->postJson(
            "/api/v1/body-paint/estimates/{$estimate['id']}/photos",
            [
                'photos' => [[
                    'asset_id' => $replacement->getKey(),
                    'damage_id' => $damageId,
                    'photo_type' => 'close',
                ]],
            ],
        )->assertOk();
        $this->postJson(
            "/api/v1/body-paint/estimates/{$estimate['id']}/resubmit",
            $this->submitPayload(),
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'auto_estimated');

        Passport::actingAs($estimator);
        $this->postJson(
            "/api/v1/admin/body-paint/estimates/{$estimate['id']}/actions",
            ['action' => 'start_review'],
        )->assertOk();
        $published = $this->postJson(
            "/api/v1/admin/body-paint/estimates/{$estimate['id']}/actions",
            $this->publishPayload($damageId),
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'estimate_ready')
            ->assertJsonPath('data.estimate.version', 1)
            ->assertJsonPath('data.estimate.low', 750000)
            ->assertJsonPath('data.estimate.high', 1150000)
            ->assertJsonPath('data.estimate.duration.min_days', 1)
            ->assertJsonPath('data.estimate.items.0.cost.labor_low', 500000);

        $revision = $this->publishPayload($damageId);
        $revision['items'][0]['labor_low'] = 600000;
        $revision['items'][0]['labor_high'] = 850000;
        $this->postJson(
            "/api/v1/admin/body-paint/estimates/{$estimate['id']}/actions",
            $revision,
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['override_reason']);
        $revision['override_reason_code'] = 'physical_review';
        $revision['override_reason'] = 'Detail foto baru menunjukkan area kerja lebih luas.';
        $this->postJson(
            "/api/v1/admin/body-paint/estimates/{$estimate['id']}/actions",
            $revision,
        )
            ->assertOk()
            ->assertJsonPath('data.estimate.version', 2)
            ->assertJsonPath('data.estimate.low', 850000);

        $this->assertDatabaseCount('body_paint_estimate_versions', 2);
        $this->assertDatabaseHas('body_paint_status_histories', [
            'estimate_id' => $estimate['id'],
            'event' => 'estimate_revised',
        ]);
        $this->assertNotNull($published->json('data.valid_until'));
    }

    public function test_customer_result_is_private_and_can_be_declined(): void
    {
        BodyPaintPriceItem::factory()->create();
        Passport::actingAs($this->customer);
        $estimate = $this->completeAndSubmit();
        $this->publishAsEstimator($estimate);

        $other = User::factory()->create();
        Passport::actingAs($other);
        $this->getJson("/api/v1/body-paint/estimates/{$estimate['id']}")
            ->assertForbidden();

        Passport::actingAs($this->customer);
        $this->postJson(
            "/api/v1/body-paint/estimates/{$estimate['id']}/decision",
            [
                'decision' => 'decline',
                'reason' => 'Belum ingin melanjutkan perbaikan.',
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'declined')
            ->assertJsonPath('data.estimate.version', 1);
    }

    public function test_booking_conversion_creates_one_linked_request_for_non_toyota_vehicle(): void
    {
        BodyPaintPriceItem::factory()->create();
        Passport::actingAs($this->customer);
        $estimate = $this->completeAndSubmit();
        $this->publishAsEstimator($estimate);

        Passport::actingAs($this->customer);
        $locationId = $this->getJson('/api/v1/body-paint/options')
            ->json('data.service_locations.0.id');
        $payload = [
            'service_location_id' => $locationId,
            'current_mileage' => 42000,
            'complaint' => 'Lanjutkan pemeriksaan dan perbaikan bumper depan.',
            'primary_slot' => [
                'date' => '2026-08-01',
                'time_window' => '07:00-09:00',
            ],
            'alternative_slot' => [
                'date' => '2026-08-01',
                'time_window' => '09:00-11:00',
            ],
            'contact_channel' => 'whatsapp',
            'service_consent' => true,
        ];
        $key = (string) Str::uuid();
        $created = $this->withHeader('Idempotency-Key', $key)
            ->postJson(
                "/api/v1/body-paint/estimates/{$estimate['id']}/request-booking",
                $payload,
            )
            ->assertOk()
            ->assertJsonPath('data.estimate.status', 'booking_requested')
            ->assertJsonPath(
                'data.booking.source.bp_estimate_id',
                $estimate['id'],
            )
            ->assertJsonPath('data.booking.status', 'awaiting_confirmation')
            ->assertJsonPath('meta.idempotent_replay', false);

        $this->withHeader('Idempotency-Key', $key)
            ->postJson(
                "/api/v1/body-paint/estimates/{$estimate['id']}/request-booking",
                $payload,
            )
            ->assertOk()
            ->assertJsonPath('data.booking.id', $created->json('data.booking.id'))
            ->assertJsonPath('meta.idempotent_replay', true);
        $this->assertDatabaseCount('toyota_service_bookings', 1);
    }

    public function test_admin_scope_and_price_matrix_preview_import_are_authorized(): void
    {
        Passport::actingAs($this->customer);
        $estimate = $this->completeAndSubmit();
        $this->getJson('/api/v1/admin/body-paint/estimates')
            ->assertForbidden();
        $this->getJson(
            "/api/v1/admin/body-paint/estimates/{$estimate['id']}",
        )->assertForbidden();

        $staff = User::factory()->create();
        $staff->assignRole('staff');
        Passport::actingAs($staff);
        $this->getJson('/api/v1/admin/body-paint/estimates')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->getJson(
            "/api/v1/admin/body-paint/estimates/{$estimate['id']}",
        )->assertForbidden();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Passport::actingAs($admin);
        $csv = $this->priceMatrixCsv();
        $invalidCsv = str_replace(
            ',front_bumper,scratch,',
            ',unknown_panel,scratch,',
            $csv,
        );
        $this->post(
            '/api/v1/admin/body-paint/price-matrix/import-preview',
            [
                'file' => UploadedFile::fake()->createWithContent(
                    'bp-invalid.csv',
                    $invalidCsv,
                ),
            ],
            ['Accept' => 'application/json'],
        )
            ->assertOk()
            ->assertJsonPath('data.valid_count', 0)
            ->assertJsonPath('data.error_count', 1);
        $this->post(
            '/api/v1/admin/body-paint/price-matrix/import',
            [
                'file' => UploadedFile::fake()->createWithContent(
                    'bp-invalid.csv',
                    $invalidCsv,
                ),
                'confirm' => true,
            ],
            ['Accept' => 'application/json'],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
        $this->assertDatabaseCount('body_paint_price_items', 0);

        $this->post(
            '/api/v1/admin/body-paint/price-matrix/import-preview',
            ['file' => UploadedFile::fake()->createWithContent('bp.csv', $csv)],
            ['Accept' => 'application/json'],
        )
            ->assertOk()
            ->assertJsonPath('data.valid_count', 1)
            ->assertJsonPath('data.error_count', 0)
            ->assertJsonPath('data.imported_count', 0);
        $this->assertDatabaseCount('body_paint_price_items', 0);

        $this->post(
            '/api/v1/admin/body-paint/price-matrix/import',
            [
                'file' => UploadedFile::fake()->createWithContent(
                    'bp.csv',
                    $csv,
                ),
                'confirm' => true,
            ],
            ['Accept' => 'application/json'],
        )
            ->assertCreated()
            ->assertJsonPath('data.imported_count', 1);
        $this->assertDatabaseHas('body_paint_price_items', [
            'matrix_code' => 'BP-AUTO2000',
            'item_code' => 'BUMPER-SCRATCH-LIGHT',
            'version' => 1,
            'source_reference' => 'Dokumen price matrix BP terverifikasi.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function damagePayload(array $overrides = []): array
    {
        return [
            'panel_code' => 'front_bumper',
            'damage_type' => 'scratch',
            'severity' => 'light',
            'note' => 'Terlihat setelah parkir.',
            ...$overrides,
        ];
    }

    /** @return array<string, mixed> */
    private function submitPayload(): array
    {
        return [
            'service_consent' => true,
            'estimate_disclaimer_accepted' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function createDraft(): array
    {
        return $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/body-paint/estimates', [
                'vehicle_id' => $this->vehicle->getKey(),
                'customer_notes' => 'Mohon estimasi panel rusak.',
            ])
            ->assertCreated()
            ->json('data');
    }

    /**
     * @param  array<string, mixed>  $damageOverrides
     * @return array<string, mixed>
     */
    private function completeAndSubmit(array $damageOverrides = []): array
    {
        $estimate = $this->createDraft();
        $updated = $this->putJson(
            "/api/v1/body-paint/estimates/{$estimate['id']}/damages",
            ['damages' => [$this->damagePayload($damageOverrides)]],
        )->assertOk()->json('data');
        $damageId = $updated['damages'][0]['id'];
        $this->postJson(
            "/api/v1/body-paint/estimates/{$estimate['id']}/photos",
            [
                'photos' => [
                    [
                        'asset_id' => $this->photoAsset()->getKey(),
                        'damage_id' => $damageId,
                        'photo_type' => 'close',
                    ],
                    [
                        'asset_id' => $this->photoAsset()->getKey(),
                        'photo_type' => 'context',
                    ],
                ],
            ],
        )->assertOk();

        return $this->postJson(
            "/api/v1/body-paint/estimates/{$estimate['id']}/submit",
            $this->submitPayload(),
        )->assertOk()->json('data');
    }

    private function photoAsset(): Asset
    {
        return Asset::factory()->create([
            'user_id' => $this->customer->getKey(),
            'storage_type' => StorageType::PrivateLocal,
            'category' => 'body-paint-estimate-photo',
            'is_protected' => true,
            'status' => AssetStatus::Active,
            'url' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function publishPayload(string $damageId): array
    {
        return [
            'action' => 'publish',
            'items' => [[
                'damage_id' => $damageId,
                'severity' => 'light',
                'work_type' => 'repair',
                'labor_low' => 500000,
                'labor_high' => 750000,
                'material_low' => 250000,
                'material_high' => 400000,
                'parts_low' => 0,
                'parts_high' => 0,
                'other_low' => 0,
                'other_high' => 0,
                'duration_min_hours' => 4,
                'duration_max_hours' => 8,
                'recommendation' => 'Repair dan spot paint bumper depan.',
            ]],
            'assumptions' => [
                'Tidak ditemukan kerusakan dudukan di balik bumper.',
            ],
            'disclaimer' => 'Estimasi awal berdasarkan foto. Harga final ditentukan setelah inspeksi fisik.',
            'valid_days' => 14,
        ];
    }

    /**
     * @param  array<string, mixed>  $estimate
     */
    private function publishAsEstimator(array $estimate): void
    {
        $estimator = User::factory()->create();
        $estimator->assignRole('admin');
        Passport::actingAs($estimator);
        $this->postJson(
            "/api/v1/admin/body-paint/estimates/{$estimate['id']}/actions",
            ['action' => 'start_review'],
        )->assertOk();
        $this->postJson(
            "/api/v1/admin/body-paint/estimates/{$estimate['id']}/actions",
            $this->publishPayload($estimate['damages'][0]['id']),
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'estimate_ready');
    }

    private function priceMatrixCsv(): string
    {
        $headers = implode(',', [
            'matrix_code',
            'item_code',
            'version',
            'service_location_code',
            'vehicle_make_id',
            'vehicle_model_id',
            'vehicle_class',
            'panel_code',
            'damage_type',
            'severity',
            'work_type',
            'labor_low',
            'labor_high',
            'material_low',
            'material_high',
            'parts_low',
            'parts_high',
            'other_low',
            'other_high',
            'duration_min_hours',
            'duration_max_hours',
            'is_high_risk',
            'effective_from',
            'effective_to',
            'source_reference',
        ]);
        $row = implode(',', [
            'BP-AUTO2000',
            'BUMPER-SCRATCH-LIGHT',
            '1',
            'auto2000-kertajaya',
            '',
            '',
            '',
            'front_bumper',
            'scratch',
            'light',
            'repair',
            '500000',
            '750000',
            '250000',
            '400000',
            '0',
            '0',
            '0',
            '0',
            '4',
            '8',
            'false',
            '2026-07-28',
            '2026-12-31',
            'Dokumen price matrix BP terverifikasi.',
        ]);

        return $headers."\n".$row."\n";
    }
}
