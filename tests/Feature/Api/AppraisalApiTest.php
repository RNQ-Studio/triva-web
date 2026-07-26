<?php

namespace Tests\Feature\Api;

use App\Exceptions\AppraisalConflictException;
use App\Models\Appraisal;
use App\Models\Asset;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AppraisalReviewService;
use App\Support\Enums\AppraisalDecision;
use App\Support\Enums\AppraisalPhotoAngle;
use App\Support\Enums\AppraisalStatus;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AppraisalApiTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->seed(RolePermissionSeeder::class);
        $this->customer = User::factory()->create([
            'phone' => '+6281234567890',
            'city' => 'Surabaya',
            'service_consent_at' => now(),
        ]);
    }

    public function test_vehicle_endpoints_require_authentication_and_validate_payload(): void
    {
        $this->getJson('/api/v1/vehicles')->assertUnauthorized();
        $this->postJson('/api/v1/vehicles', [])->assertUnauthorized();

        Passport::actingAs($this->customer);
        $this->postJson('/api/v1/vehicles', [
            ...$this->vehiclePayload(),
            'year' => 1900,
            'mileage' => -1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['year', 'mileage']);
    }

    public function test_customer_can_create_list_and_update_owned_vehicle(): void
    {
        Passport::actingAs($this->customer);

        $created = $this->postJson('/api/v1/vehicles', $this->vehiclePayload())
            ->assertCreated()
            ->assertJsonPath('data.make', 'Toyota')
            ->assertJsonPath('data.model', 'Avanza');

        $vehicleId = $created->json('data.id');
        $this->getJson('/api/v1/vehicles')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->putJson('/api/v1/vehicles/'.$vehicleId, [
            ...$this->vehiclePayload(),
            'mileage' => 43000,
        ])
            ->assertOk()
            ->assertJsonPath('data.mileage', 43000);

        $other = User::factory()->create();
        Passport::actingAs($other);
        $this->getJson('/api/v1/vehicles/'.$vehicleId)->assertForbidden();
        $this->putJson('/api/v1/vehicles/'.$vehicleId, $this->vehiclePayload())->assertForbidden();
    }

    public function test_appraisal_submit_requires_complete_condition_and_exactly_five_owned_photos(): void
    {
        Passport::actingAs($this->customer);
        $appraisal = $this->createDraft();
        $key = (string) Str::uuid();

        $this->withHeader('Idempotency-Key', $key)
            ->postJson("/api/v1/appraisals/{$appraisal->id}/submit", [
                'service_consent' => true,
            ])
            ->assertConflict()
            ->assertJsonPath('code', 'APPRAISAL_STATE_CONFLICT');

        $this->putJson("/api/v1/appraisals/{$appraisal->id}/vehicle-condition", $this->conditionPayload())
            ->assertOk();

        $foreignAsset = Asset::factory()->create([
            'user_id' => User::factory(),
            'category' => 'appraisal-photo',
        ]);
        $this->postJson("/api/v1/appraisals/{$appraisal->id}/photos", [
            'photos' => [[
                'angle' => AppraisalPhotoAngle::Front->value,
                'asset_id' => $foreignAsset->id,
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photos.0.asset_id']);

        $this->attachFivePhotos($appraisal);

        $this->withHeader('Idempotency-Key', $key)
            ->postJson("/api/v1/appraisals/{$appraisal->id}/submit", [
                'service_consent' => true,
                'marketing_consent' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', AppraisalStatus::CollectingMarketData->value)
            ->assertJsonPath('data.reference_no', $appraisal->reference_no)
            ->assertJsonCount(3, 'data.timeline');

        $this->withHeader('Idempotency-Key', $key)
            ->postJson("/api/v1/appraisals/{$appraisal->id}/submit", [
                'service_consent' => true,
                'marketing_consent' => false,
            ])
            ->assertOk()
            ->assertJsonCount(3, 'data.timeline');
    }

    public function test_other_customer_cannot_open_or_mutate_appraisal(): void
    {
        Passport::actingAs($this->customer);
        $appraisal = $this->createDraft();

        $other = User::factory()->create();
        Passport::actingAs($other);

        $this->getJson("/api/v1/appraisals/{$appraisal->id}")->assertForbidden();
        $this->putJson(
            "/api/v1/appraisals/{$appraisal->id}/vehicle-condition",
            $this->conditionPayload(),
        )->assertForbidden();
    }

    public function test_rejected_photo_is_replaced_on_same_request_and_resubmitted(): void
    {
        Passport::actingAs($this->customer);
        $appraisal = $this->submittedAppraisal();
        $appraiser = User::factory()->create();
        $appraiser->assignRole('staff');

        $reviews = app(AppraisalReviewService::class);
        $reviews->startReview($appraisal, $appraiser);
        $reviews->requestPhotoCorrection(
            $appraisal->refresh(),
            $appraiser,
            AppraisalPhotoAngle::RightSide,
            'Foto terlalu buram. Ambil ulang dari jarak 2–3 meter.',
        );

        $this->getJson("/api/v1/appraisals/{$appraisal->id}")
            ->assertOk()
            ->assertJsonPath('data.status', AppraisalStatus::NeedsCustomerAction->value)
            ->assertJsonFragment([
                'angle' => AppraisalPhotoAngle::RightSide->value,
                'review_status' => 'rejected',
            ]);

        $replacement = Asset::factory()->create([
            'user_id' => $this->customer->getKey(),
            'category' => 'appraisal-photo',
            'url' => null,
            'is_protected' => true,
        ]);
        $this->postJson("/api/v1/appraisals/{$appraisal->id}/photos", [
            'photos' => [[
                'angle' => AppraisalPhotoAngle::RightSide->value,
                'asset_id' => $replacement->id,
            ]],
        ])->assertOk();

        $this->postJson("/api/v1/appraisals/{$appraisal->id}/resubmit")
            ->assertOk()
            ->assertJsonPath('data.status', AppraisalStatus::UnderAppraiserReview->value);

        $this->assertDatabaseCount('appraisal_photos', 6);
        $this->assertDatabaseHas('appraisal_photos', [
            'appraisal_id' => $appraisal->id,
            'angle' => AppraisalPhotoAngle::RightSide->value,
            'version' => 2,
            'is_current' => true,
        ]);
    }

    public function test_result_is_hidden_until_published_then_decision_carries_forward_contract(): void
    {
        Passport::actingAs($this->customer);
        $appraisal = $this->submittedAppraisal();

        $this->getJson("/api/v1/appraisals/{$appraisal->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.result.market_price');

        $appraiser = User::factory()->create();
        $appraiser->assignRole('staff');
        $reviews = app(AppraisalReviewService::class);
        $reviews->startReview($appraisal, $appraiser);
        $reviews->publishResult(
            $appraisal->refresh(),
            $appraiser,
            [
                'market_low' => 178000000,
                'market_mid' => 185000000,
                'market_high' => 192000000,
                'trade_in_low' => 168000000,
                'trade_in_high' => 176000000,
                'data_as_of' => now(),
                'valid_until' => now()->addDays(7),
                'requires_physical_inspection' => true,
                'disclaimer' => 'Hasil merupakan indikasi dan belum merupakan penawaran final.',
                'adjustments' => [['label' => 'Kondisi kendaraan']],
            ],
            $this->comparablePayloads(6),
        );

        $this->getJson("/api/v1/appraisals/{$appraisal->id}")
            ->assertOk()
            ->assertJsonPath('data.status', AppraisalStatus::ResultReady->value)
            ->assertJsonPath('data.result.trade_in_estimate.low', 168000000)
            ->assertJsonPath('data.result.market_price.high', 192000000)
            ->assertJsonPath('data.result.confidence', 'medium')
            ->assertJsonPath('data.result.comparable_count', 6);

        $this->postJson("/api/v1/appraisals/{$appraisal->id}/decision", [
            'decision' => AppraisalDecision::Accepted->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', AppraisalStatus::AcceptedByCustomer->value)
            ->assertJsonPath('data.continuation.type', 'credit_simulation')
            ->assertJsonPath('data.continuation.vehicle_id', $appraisal->vehicle_id)
            ->assertJsonPath('data.continuation.suggested_trade_in_low', 168000000);
    }

    public function test_result_publication_requires_provenance_and_valid_price_order(): void
    {
        $appraisal = $this->submittedAppraisal();
        $appraiser = User::factory()->create();
        $appraiser->assignRole('staff');
        $reviews = app(AppraisalReviewService::class);
        $reviews->startReview($appraisal, $appraiser);

        $this->expectException(AppraisalConflictException::class);
        $reviews->publishResult(
            $appraisal->refresh(),
            $appraiser,
            [
                'market_low' => 200000000,
                'market_mid' => 185000000,
                'market_high' => 192000000,
                'trade_in_low' => 168000000,
                'trade_in_high' => 176000000,
                'data_as_of' => now(),
                'valid_until' => now()->addDays(7),
                'requires_physical_inspection' => true,
                'disclaimer' => 'Indikatif.',
            ],
            [],
        );
    }

    private function createDraft(): Appraisal
    {
        $vehicle = Vehicle::factory()->create(['user_id' => $this->customer->getKey()]);
        $response = $this->postJson('/api/v1/appraisals', [
            'vehicle_id' => $vehicle->id,
        ])->assertCreated();

        return Appraisal::query()->findOrFail($response->json('data.id'));
    }

    private function submittedAppraisal(): Appraisal
    {
        Passport::actingAs($this->customer);
        $appraisal = $this->createDraft();
        $this->putJson(
            "/api/v1/appraisals/{$appraisal->id}/vehicle-condition",
            $this->conditionPayload(),
        )->assertOk();
        $this->attachFivePhotos($appraisal);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/appraisals/{$appraisal->id}/submit", [
                'service_consent' => true,
            ])
            ->assertOk();

        return $appraisal->refresh();
    }

    private function attachFivePhotos(Appraisal $appraisal): void
    {
        $assets = Asset::factory()->count(5)->create([
            'user_id' => $this->customer->getKey(),
            'category' => 'appraisal-photo',
            'url' => null,
            'is_protected' => true,
        ]);

        $photos = collect(AppraisalPhotoAngle::cases())
            ->values()
            ->map(fn (AppraisalPhotoAngle $angle, int $index): array => [
                'angle' => $angle->value,
                'asset_id' => $assets[$index]->id,
            ])
            ->all();

        $this->postJson("/api/v1/appraisals/{$appraisal->id}/photos", [
            'photos' => $photos,
        ])->assertOk();
    }

    /** @return array<string, mixed> */
    private function vehiclePayload(): array
    {
        return [
            'make' => 'Toyota',
            'model' => 'Avanza',
            'variant' => '1.5 G',
            'year' => 2022,
            'transmission' => 'automatic',
            'fuel_type' => 'gasoline',
            'mileage' => 42500,
            'color' => 'Putih',
            'license_plate' => 'L 1234 TRV',
            'city' => 'Surabaya',
        ];
    }

    /** @return array<string, string> */
    private function conditionPayload(): array
    {
        return [
            'tax_status' => 'active',
            'flood_history' => 'no',
            'major_accident_history' => 'no',
            'service_history' => 'complete',
            'ownership' => 'first',
        ];
    }

    /** @return list<array<string, mixed>> */
    private function comparablePayloads(int $count): array
    {
        return collect(range(1, $count))
            ->map(fn (int $index): array => [
                'source_code' => 'manual_appraiser',
                'external_reference_hash' => hash('sha256', 'reference-'.$index),
                'make' => 'Toyota',
                'model' => 'Avanza',
                'variant' => '1.5 G',
                'year' => 2022,
                'mileage' => 40000 + ($index * 1000),
                'listing_price' => 178000000 + ($index * 2000000),
                'city' => 'Surabaya',
                'observed_at' => now()->subDays($index),
                'similarity_score' => 0.9000,
                'is_outlier' => false,
                'metadata' => ['provenance' => 'manual_csv'],
            ])
            ->all();
    }
}
