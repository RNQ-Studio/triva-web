<?php

namespace Tests\Feature\Api;

use App\Models\CreditSimulation;
use App\Models\User;
use App\Support\Enums\CreditSimulationStatus;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AdminCreditSimulationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->customer = User::factory()->create([
            'name' => 'Pelanggan Kredit',
            'phone' => '+628222222222',
        ]);
    }

    public function test_listing_requires_authentication_and_credit_permission(): void
    {
        $this->getJson('/api/v1/admin/credit/simulations')->assertUnauthorized();

        Passport::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/credit/simulations')->assertForbidden();

        Passport::actingAs($this->admin);
        $this->getJson('/api/v1/admin/credit/simulations')->assertOk();
    }

    public function test_admin_can_list_and_filter_every_saved_simulation(): void
    {
        $mine = CreditSimulation::factory()->for($this->customer)->create();
        $other = CreditSimulation::factory()->create([
            'status' => CreditSimulationStatus::Expired,
        ]);

        Passport::actingAs($this->admin);

        $this->getJson('/api/v1/admin/credit/simulations')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/admin/credit/simulations?status=expired')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $other->getKey());

        $this->getJson(
            '/api/v1/admin/credit/simulations?search='.$mine->reference_no,
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer.name', 'Pelanggan Kredit')
            ->assertJsonPath('data.0.totals.monthly_installment', 5050000)
            ->assertJsonPath('data.0.totals.tenor_months', 60);

        $this->getJson('/api/v1/admin/credit/simulations?sort=harga')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort']);
    }

    public function test_admin_can_open_simulation_detail_of_another_customer(): void
    {
        $simulation = CreditSimulation::factory()
            ->for($this->customer)
            ->create();

        Passport::actingAs($this->admin);

        $this->getJson(
            "/api/v1/admin/credit/simulations/{$simulation->getKey()}",
        )
            ->assertOk()
            ->assertJsonPath('data.id', $simulation->getKey())
            ->assertJsonPath('data.customer.phone', '+628222222222')
            ->assertJsonPath('data.totals.total_payment', 384500000);
    }

    public function test_options_expose_status_choices(): void
    {
        Passport::actingAs($this->admin);

        $this->getJson('/api/v1/admin/credit/simulations/options')
            ->assertOk()
            ->assertJsonPath('data.statuses.0.value', 'saved');
    }
}
