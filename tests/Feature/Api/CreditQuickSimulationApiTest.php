<?php

namespace Tests\Feature\Api;

use App\Models\CreditProgram;
use App\Models\CreditSimulation;
use App\Models\Notification;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CreditQuickSimulationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private CreditProgram $program;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->seed(RolePermissionSeeder::class);
        CreditProgram::query()->delete();
        $this->customer = User::factory()->create(['name' => 'Rina Pelanggan']);
        $this->program = CreditProgram::factory()->create([
            'vehicle_model' => 'Veloz Hybrid',
            'vehicle_variant' => '1.5 Q HEV CVT TSS',
            'otr_price' => 405_000_000,
            'unit_key' => 'veloz_hybrid',
        ]);
    }

    public function test_rate_card_and_quick_simulation_require_authentication(): void
    {
        $this->getJson('/api/v1/credit/quick/rate-card')->assertUnauthorized();
        $this->postJson('/api/v1/credit/quick')->assertUnauthorized();
    }

    public function test_rate_card_exposes_the_worksheet_matrices(): void
    {
        Passport::actingAs($this->customer);

        $this->getJson('/api/v1/credit/quick/rate-card')
            ->assertOk()
            ->assertJsonPath('data.dp_percent_options', [20, 25, 30])
            ->assertJsonPath('data.tenor_years_options', [1, 2, 3, 4, 5])
            ->assertJsonPath('data.administration_fee', 1_000_000)
            ->assertJsonPath('data.liability_insurance_fee', 400_000)
            ->assertJsonPath('data.interest_rates.reg2.30.5', 900)
            ->assertJsonPath('data.insurance_rates.3.rates.5', 536);
    }

    public function test_quick_simulation_is_saved_and_admins_are_notified(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $inactiveAdmin = User::factory()->create(['is_active' => false]);
        $inactiveAdmin->assignRole('admin');
        Passport::actingAs($this->customer);

        $response = $this->postJson('/api/v1/credit/quick', [
            'program_id' => $this->program->getKey(),
            'otr_price' => 183_260_000,
            'dp_percent' => 30,
            'tenor_years' => 5,
        ])->assertCreated();

        $response
            ->assertJsonPath('data.formula_version', 'acc-flat-v1')
            ->assertJsonPath('data.inputs.dp_percent', 30)
            ->assertJsonPath('data.inputs.tenor_years', 5)
            ->assertJsonPath('data.calculation.annual_flat_rate_basis_points', 900)
            ->assertJsonPath('data.calculation.down_payment', 54_978_000)
            ->assertJsonPath('data.program.vehicle_model', 'Veloz Hybrid')
            ->assertJsonPath('data.program.otr_price', 183_260_000);
        self::assertGreaterThan(0, $response->json('data.calculation.monthly_installment'));

        $simulation = CreditSimulation::query()->firstOrFail();
        self::assertSame($this->customer->getKey(), $simulation->user_id);
        self::assertSame(183_260_000, $simulation->otr_price);
        self::assertSame(60, $simulation->tenor_months);
        self::assertSame($response->json('data.calculation.monthly_installment'), $simulation->monthly_installment);

        self::assertDatabaseHas(Notification::class, [
            'user_id' => $admin->getKey(),
            'type' => 'credit_simulation',
            'title' => 'Simulasi kredit baru',
        ]);
        self::assertDatabaseMissing(Notification::class, [
            'user_id' => $inactiveAdmin->getKey(),
        ]);
        self::assertDatabaseMissing(Notification::class, [
            'user_id' => $this->customer->getKey(),
        ]);
        $notification = Notification::query()->where('user_id', $admin->getKey())->firstOrFail();
        self::assertSame($simulation->getKey(), $notification->data['credit_simulation_id']);
        self::assertStringContainsString('Rina Pelanggan', $notification->body);
        self::assertStringContainsString('DP 30%', $notification->body);
    }

    public function test_quick_simulation_validates_dp_tenor_and_program(): void
    {
        Passport::actingAs($this->customer);

        $this->postJson('/api/v1/credit/quick', [
            'program_id' => $this->program->getKey(),
            'otr_price' => 183_260_000,
            'dp_percent' => 15,
            'tenor_years' => 6,
        ])->assertUnprocessable()->assertJsonValidationErrors(['dp_percent', 'tenor_years']);

        $expired = CreditProgram::factory()->create(['effective_to' => now()->subDay()]);
        $this->postJson('/api/v1/credit/quick', [
            'program_id' => $expired->getKey(),
            'otr_price' => 183_260_000,
            'dp_percent' => 20,
            'tenor_years' => 3,
        ])->assertNotFound();
    }

    public function test_program_catalog_exposes_unit_key_and_image(): void
    {
        $this->program->update(['image_path' => 'credit-units/veloz.jpg']);
        Passport::actingAs($this->customer);

        $response = $this->getJson('/api/v1/credit/programs')->assertOk();

        self::assertSame('veloz_hybrid', $response->json('data.0.unit_key'));
        self::assertStringEndsWith('/storage/credit-units/veloz.jpg', (string) $response->json('data.0.image_url'));
    }
}
