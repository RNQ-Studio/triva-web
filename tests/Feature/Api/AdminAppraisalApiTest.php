<?php

namespace Tests\Feature\Api;

use App\Models\Appraisal;
use App\Models\User;
use App\Support\Enums\AppraisalStatus;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AdminAppraisalApiTest extends TestCase
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
            'name' => 'Pelanggan Appraisal',
            'phone' => '+628111111111',
        ]);
    }

    public function test_listing_requires_authentication_and_appraisal_permission(): void
    {
        $this->getJson('/api/v1/admin/appraisals')->assertUnauthorized();

        $customer = User::factory()->create();
        Passport::actingAs($customer);
        $this->getJson('/api/v1/admin/appraisals')->assertForbidden();

        Passport::actingAs($this->admin);
        $this->getJson('/api/v1/admin/appraisals')->assertOk();
    }

    public function test_admin_can_list_every_customer_appraisal_and_filter_it(): void
    {
        $mine = $this->appraisal(AppraisalStatus::Submitted);
        $other = Appraisal::factory()->create([
            'status' => AppraisalStatus::ResultReady,
        ]);

        Passport::actingAs($this->admin);

        $this->getJson('/api/v1/admin/appraisals')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/admin/appraisals?status=submitted')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->getKey())
            ->assertJsonPath('data.0.customer.name', 'Pelanggan Appraisal');

        $this->getJson(
            '/api/v1/admin/appraisals?user_id='.$other->user_id,
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $other->getKey());

        $this->getJson('/api/v1/admin/appraisals?search=Pelanggan+Appraisal')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->getKey());

        $this->getJson('/api/v1/admin/appraisals?search='.$mine->reference_no)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference_no', $mine->reference_no);

        $this->getJson('/api/v1/admin/appraisals?status=tidak_ada')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_admin_can_open_appraisal_detail_belonging_to_another_customer(): void
    {
        $appraisal = $this->appraisal(AppraisalStatus::Submitted);

        Passport::actingAs($this->admin);

        $this->getJson("/api/v1/admin/appraisals/{$appraisal->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.id', $appraisal->getKey())
            ->assertJsonPath('data.reference_no', $appraisal->reference_no)
            ->assertJsonPath('data.customer.phone', '+628111111111')
            ->assertJsonPath(
                'data.condition_percentage',
                $appraisal->refresh()->condition_percentage,
            );
    }

    public function test_options_expose_status_choices(): void
    {
        Passport::actingAs($this->admin);

        $this->getJson('/api/v1/admin/appraisals/options')
            ->assertOk()
            ->assertJsonPath('data.statuses.0.value', 'draft');
    }

    private function appraisal(AppraisalStatus $status): Appraisal
    {
        return Appraisal::factory()
            ->for($this->customer)
            ->create(['status' => $status]);
    }
}
