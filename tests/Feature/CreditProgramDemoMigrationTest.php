<?php

namespace Tests\Feature;

use App\Models\CreditProgram;
use App\Models\CreditSimulation;
use App\Models\User;
use App\Support\Enums\CreditProgramStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CreditProgramDemoMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-03 03:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_migration_provides_an_effective_and_explicitly_labelled_demo_program(): void
    {
        $this->migration()->up();
        $program = CreditProgram::query()
            ->where('program_code', 'TRIVA-DEMO-CREDIT')
            ->firstOrFail();

        $this->assertDatabaseCount('credit_programs', 1);
        $this->assertSame(
            '00000000-0000-4000-8000-000000000401',
            $program->getKey(),
        );
        $this->assertTrue($program->is_demo);
        $this->assertSame(CreditProgramStatus::Approved, $program->status);
        $this->assertStringContainsString(
            'Bukan Penawaran Kredit',
            $program->program_name,
        );
        $this->assertStringContainsString('dummy', $program->source_reference);
        $this->assertCount(3, $program->tenor_options);
        $this->assertSame(
            '2026-08-31',
            $program->effective_to?->toDateString(),
        );
        $this->assertTrue(
            CreditProgram::query()->effective()->whereKey($program)->exists(),
        );
    }

    public function test_down_removes_an_unreferenced_demo_program(): void
    {
        $program = CreditProgram::query()
            ->where('program_code', 'TRIVA-DEMO-CREDIT')
            ->firstOrFail();

        $this->migration()->down();

        $this->assertDatabaseMissing('credit_programs', [
            'id' => $program->getKey(),
        ]);
    }

    public function test_seeded_demo_program_supports_the_customer_api_flow(): void
    {
        $program = CreditProgram::query()
            ->where('program_code', 'TRIVA-DEMO-CREDIT')
            ->firstOrFail();
        Passport::actingAs(User::factory()->create());

        $this->getJson('/api/v1/credit/programs')
            ->assertOk()
            ->assertJsonPath('data.0.id', $program->getKey())
            ->assertJsonPath('data.0.is_demo', true);

        $payload = [
            'program_id' => $program->getKey(),
            'otr_price' => 320000000,
            'cash_down_payment' => 54000000,
            'manual_trade_in_value' => 0,
            'use_trade_in_as_dp' => false,
            'old_vehicle_payoff' => 0,
            'tenor_months' => 60,
            'accept_expired_appraisal' => false,
            'campaign_source' => 'credit_demo_test',
        ];

        $this->postJson('/api/v1/credit/simulations/calculate', $payload)
            ->assertOk()
            ->assertJsonPath('data.program.is_demo', true)
            ->assertJsonPath('data.calculation.principal', 256000000);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/credit/simulations', $payload)
            ->assertCreated()
            ->assertJsonPath('data.program.is_demo', true)
            ->assertJsonPath('data.calculation.principal', 256000000);
    }

    public function test_down_stops_before_removing_a_demo_program_used_by_a_snapshot(): void
    {
        $program = CreditProgram::query()
            ->where('program_code', 'TRIVA-DEMO-CREDIT')
            ->firstOrFail();
        CreditSimulation::factory()->create([
            'credit_program_id' => $program->getKey(),
        ]);

        try {
            $this->migration()->down();
            $this->fail('Rollback seharusnya berhenti untuk program yang sudah dipakai.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString(
                'Rollback dihentikan',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseHas('credit_programs', [
            'id' => $program->getKey(),
            'is_demo' => true,
        ]);
    }

    private function migration(): Migration
    {
        return require database_path(
            'migrations/2026_08_03_000100_seed_credit_demo_program.php',
        );
    }
}
