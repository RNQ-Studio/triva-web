<?php

namespace Tests\Feature;

use App\Models\CreditProgram;
use App\Models\User;
use App\Support\Enums\CreditProgramStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CreditProgramSpektaMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-25 03:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_spekta_programs_are_effective_and_recommend_a_twenty_percent_down_payment(): void
    {
        $programs = CreditProgram::query()->where('package_code', 'spekta')->get();

        self::assertCount(4, $programs);
        foreach ($programs as $program) {
            self::assertSame(CreditProgramStatus::Approved, $program->status);
            self::assertSame(2000, $program->recommended_dp_basis_points);
            self::assertTrue(
                CreditProgram::query()->effective()->whereKey($program)->exists(),
                $program->program_code.' seharusnya aktif.',
            );
        }
    }

    public function test_the_catalog_exposes_the_recommended_down_payment_amount(): void
    {
        Passport::actingAs(User::factory()->create());
        $program = CreditProgram::query()
            ->where('program_code', 'SPEKTA-AVANZA-2026')
            ->firstOrFail();

        $response = $this->getJson('/api/v1/credit/programs')->assertOk();
        $listed = collect($response->json('data'))
            ->firstWhere('id', $program->getKey());

        self::assertNotNull($listed);
        self::assertSame('spekta', $listed['package_code']);
        self::assertSame(2000, $listed['recommended_dp_basis_points']);
        // 20% dari OTR 320 juta.
        self::assertSame(64_000_000, $listed['recommended_dp_amount']);
    }

    public function test_a_spekta_simulation_runs_on_the_recommended_down_payment(): void
    {
        Passport::actingAs(User::factory()->create());
        $program = CreditProgram::query()
            ->where('program_code', 'SPEKTA-AVANZA-2026')
            ->firstOrFail();

        $this->postJson('/api/v1/credit/simulations/calculate', [
            'program_id' => $program->getKey(),
            'otr_price' => 320_000_000,
            'cash_down_payment' => 64_000_000,
            'manual_trade_in_value' => 0,
            'use_trade_in_as_dp' => false,
            'old_vehicle_payoff' => 0,
            'tenor_months' => 36,
            'accept_expired_appraisal' => false,
            'campaign_source' => 'spekta_test',
        ])
            ->assertOk()
            ->assertJsonPath('data.calculation.total_down_payment', 64_000_000)
            ->assertJsonPath('data.calculation.principal', 256_000_000);
    }

    public function test_a_down_payment_below_twenty_percent_is_rejected(): void
    {
        Passport::actingAs(User::factory()->create());
        $program = CreditProgram::query()
            ->where('program_code', 'SPEKTA-AVANZA-2026')
            ->firstOrFail();

        $this->postJson('/api/v1/credit/simulations/calculate', [
            'program_id' => $program->getKey(),
            'otr_price' => 320_000_000,
            'cash_down_payment' => 40_000_000,
            'manual_trade_in_value' => 0,
            'use_trade_in_as_dp' => false,
            'old_vehicle_payoff' => 0,
            'tenor_months' => 36,
            'accept_expired_appraisal' => false,
            'campaign_source' => 'spekta_test',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cash_down_payment']);
    }
}
