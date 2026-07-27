<?php

namespace Tests\Feature\Api;

use App\Models\Appraisal;
use App\Models\CreditProgram;
use App\Models\User;
use App\Support\Enums\AppraisalConfidence;
use App\Support\Enums\AppraisalStatus;
use App\Support\Enums\CreditProgramStatus;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CreditSimulationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private CreditProgram $program;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-27 03:00:00', 'UTC'));
        Queue::fake();
        $this->seed(RolePermissionSeeder::class);
        $this->customer = User::factory()->create([
            'phone' => '+6281234567890',
            'city' => 'Surabaya',
            'service_consent_at' => now(),
        ]);
        $this->program = CreditProgram::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_credit_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/credit/vehicles')->assertUnauthorized();
        $this->getJson('/api/v1/credit/programs')->assertUnauthorized();
        $this->postJson('/api/v1/credit/simulations/calculate')
            ->assertUnauthorized();
        $this->getJson('/api/v1/credit/simulations')->assertUnauthorized();
        $this->postJson('/api/v1/credit/simulations')->assertUnauthorized();
    }

    public function test_catalog_only_exposes_effective_approved_programs_and_filters(): void
    {
        CreditProgram::factory()->create([
            'status' => CreditProgramStatus::Draft,
            'vehicle_model' => 'Innova Zenix',
        ]);
        CreditProgram::factory()->create([
            'status' => CreditProgramStatus::Approved,
            'effective_to' => '2026-07-26',
            'vehicle_model' => 'Yaris Cross',
        ]);
        Passport::actingAs($this->customer);

        $this->getJson('/api/v1/credit/vehicles?per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.model', 'Avanza')
            ->assertJsonPath('meta.pagination.total', 1);

        $this->getJson('/api/v1/credit/programs?'.http_build_query([
            'city' => 'Surabaya',
            'vehicle_model' => 'Avanza',
            'vehicle_variant' => '1.5 G CVT',
            'model_year' => 2026,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->program->getKey())
            ->assertJsonPath('data.0.vehicle.otr_price', 320000000)
            ->assertJsonPath('data.0.minimum_dp_amount', 64000000)
            ->assertJsonPath('data.0.tenor_options.1.tenor_months', 60)
            ->assertJsonPath('data.0.is_estimate', true);
    }

    public function test_calculator_uses_integer_flat_formula_and_exposes_full_breakdown(): void
    {
        Passport::actingAs($this->customer);

        $this->postJson(
            '/api/v1/credit/simulations/calculate',
            $this->validPayload([
                'cash_down_payment' => 20000000,
                'manual_trade_in_value' => 100000000,
                'old_vehicle_payoff' => 20000000,
                'use_trade_in_as_dp' => true,
            ]),
        )
            ->assertOk()
            ->assertJsonPath('data.inputs.trade_in_value', 100000000)
            ->assertJsonPath(
                'data.calculation.trade_in_equity',
                80000000,
            )
            ->assertJsonPath(
                'data.calculation.total_down_payment',
                110000000,
            )
            ->assertJsonPath('data.calculation.principal', 210000000)
            ->assertJsonPath(
                'data.calculation.total_flat_interest',
                55125000,
            )
            ->assertJsonPath(
                'data.calculation.monthly_installment',
                4418750,
            )
            ->assertJsonPath(
                'data.calculation.initial_payment',
                31500000,
            )
            ->assertJsonPath('data.calculation.total_payment', 296625000)
            ->assertJsonPath('data.formula_version', 'flat-v1')
            ->assertJsonPath('data.is_estimate', true);
    }

    public function test_calculator_rejects_stale_price_invalid_tenor_and_dp_bounds(): void
    {
        Passport::actingAs($this->customer);

        $this->postJson(
            '/api/v1/credit/simulations/calculate',
            $this->validPayload(['otr_price' => 319000000]),
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['otr_price']);
        $this->postJson(
            '/api/v1/credit/simulations/calculate',
            $this->validPayload(['tenor_months' => 48]),
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tenor_months']);
        $this->postJson(
            '/api/v1/credit/simulations/calculate',
            $this->validPayload(['cash_down_payment' => 20000000]),
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cash_down_payment']);
        $this->postJson(
            '/api/v1/credit/simulations/calculate',
            $this->validPayload(['cash_down_payment' => 300000000]),
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cash_down_payment']);
    }

    public function test_appraisal_trade_in_requires_ownership_result_and_expiry_confirmation(): void
    {
        $appraisal = Appraisal::factory()->create([
            'user_id' => $this->customer->getKey(),
            'status' => AppraisalStatus::ResultReady,
        ]);
        $appraisal->results()->create([
            'version' => 1,
            'market_low' => 120000000,
            'market_mid' => 130000000,
            'market_high' => 140000000,
            'trade_in_low' => 100000000,
            'trade_in_high' => 120000000,
            'confidence' => AppraisalConfidence::High,
            'comparable_count' => 5,
            'data_as_of' => now()->subMonth(),
            'valid_until' => now()->subDay(),
            'requires_physical_inspection' => true,
            'disclaimer' => 'Perlu inspeksi fisik.',
            'published_by' => User::factory()->create()->getKey(),
            'published_at' => now()->subMonth(),
        ]);
        Passport::actingAs($this->customer);
        $payload = $this->validPayload([
            'cash_down_payment' => 20000000,
            'trade_in_appraisal_id' => $appraisal->getKey(),
            'use_trade_in_as_dp' => true,
        ]);

        $this->postJson('/api/v1/credit/simulations/calculate', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['accept_expired_appraisal']);
        $payload['accept_expired_appraisal'] = true;
        $this->postJson('/api/v1/credit/simulations/calculate', $payload)
            ->assertOk()
            ->assertJsonPath('data.inputs.trade_in_value', 110000000)
            ->assertJsonCount(1, 'data.warnings');

        $other = User::factory()->create();
        $foreign = Appraisal::factory()->create([
            'user_id' => $other->getKey(),
        ]);
        $payload['trade_in_appraisal_id'] = $foreign->getKey();
        $this->postJson('/api/v1/credit/simulations/calculate', $payload)
            ->assertForbidden();
    }

    public function test_save_is_idempotent_snapshots_program_and_protects_customer_data(): void
    {
        Passport::actingAs($this->customer);
        $key = (string) Str::uuid();
        $payload = $this->validPayload();

        $created = $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/credit/simulations', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'saved')
            ->assertJsonPath(
                'data.program.program_name',
                'Program Flat Test',
            )
            ->assertJsonPath(
                'data.calculation.monthly_installment',
                5050000,
            )
            ->assertJsonPath('meta.idempotent_replay', false);

        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/credit/simulations', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $created->json('data.id'))
            ->assertJsonPath('meta.idempotent_replay', true);
        $this->assertDatabaseCount('credit_simulations', 1);

        $changed = $payload;
        $changed['cash_down_payment'] = 80000000;
        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/credit/simulations', $changed)
            ->assertConflict()
            ->assertJsonPath('code', 'CREDIT_IDEMPOTENCY_CONFLICT');

        $this->getJson('/api/v1/credit/simulations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $created->json('data.id'));

        Passport::actingAs(User::factory()->create());
        $this->getJson(
            '/api/v1/credit/simulations/'.$created->json('data.id')
        )->assertForbidden();
    }

    public function test_comparison_group_is_limited_to_three_saved_scenarios(): void
    {
        Passport::actingAs($this->customer);
        $group = (string) Str::uuid();
        for ($index = 0; $index < 3; $index++) {
            $this->withHeader('Idempotency-Key', (string) Str::uuid())
                ->postJson('/api/v1/credit/simulations', $this->validPayload([
                    'comparison_group_id' => $group,
                    'cash_down_payment' => 70000000 + ($index * 10000000),
                ]))
                ->assertCreated();
        }

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/credit/simulations', $this->validPayload([
                'comparison_group_id' => $group,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['comparison_group_id']);
    }

    public function test_comparison_group_rejects_duplicate_financial_scenario(): void
    {
        Passport::actingAs($this->customer);
        $group = (string) Str::uuid();
        $payload = $this->validPayload([
            'comparison_group_id' => $group,
        ]);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/credit/simulations', $payload)
            ->assertCreated();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/credit/simulations', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['comparison_group_id']);
        $this->assertDatabaseCount('credit_simulations', 1);
    }

    public function test_follow_up_requires_consent_and_is_created_once(): void
    {
        Passport::actingAs($this->customer);
        $simulation = $this->withHeader(
            'Idempotency-Key',
            (string) Str::uuid(),
        )->postJson(
            '/api/v1/credit/simulations',
            $this->validPayload(),
        )->assertCreated()->json('data.id');
        $path = "/api/v1/credit/simulations/{$simulation}/request-follow-up";

        $this->postJson($path, [
            'follow_up_consent' => false,
            'consent_version' => 'credit-follow-up-v1',
            'contact_channel' => 'whatsapp',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['follow_up_consent']);

        $payload = [
            'follow_up_consent' => true,
            'consent_version' => 'credit-follow-up-v1',
            'contact_channel' => 'whatsapp',
            'campaign_source' => 'triva_credit_result',
        ];
        $this->postJson($path, $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'lead_created')
            ->assertJsonPath('data.follow_up.status', 'new')
            ->assertJsonPath('meta.idempotent_replay', false);
        $this->postJson($path, $payload)
            ->assertOk()
            ->assertJsonPath('meta.idempotent_replay', true);
        $this->assertDatabaseCount('credit_follow_up_leads', 1);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->customer->getKey(),
            'type' => 'credit_simulation',
        ]);

        Passport::actingAs(User::factory()->create());
        $this->postJson($path, $payload)->assertForbidden();
    }

    public function test_saved_snapshot_remains_readable_after_program_expires(): void
    {
        Passport::actingAs($this->customer);
        $simulationId = $this->withHeader(
            'Idempotency-Key',
            (string) Str::uuid(),
        )->postJson(
            '/api/v1/credit/simulations',
            $this->validPayload(),
        )->assertCreated()->json('data.id');
        $this->program->update([
            'status' => CreditProgramStatus::Inactive,
        ]);

        $this->getJson("/api/v1/credit/simulations/{$simulationId}")
            ->assertOk()
            ->assertJsonPath(
                'data.program.program_name',
                'Program Flat Test',
            );
    }

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return [
            'program_id' => $this->program->getKey(),
            'otr_price' => 320000000,
            'cash_down_payment' => 70000000,
            'manual_trade_in_value' => 0,
            'use_trade_in_as_dp' => false,
            'old_vehicle_payoff' => 0,
            'tenor_months' => 60,
            'accept_expired_appraisal' => false,
            'campaign_source' => 'triva_credit',
            ...$overrides,
        ];
    }
}
