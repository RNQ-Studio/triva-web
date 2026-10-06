<?php

namespace Tests\Feature\Api;

use App\Models\ToyotaServicePackage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AdminToyotaServicePackageApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-06 03:00:00', 'UTC'));
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_endpoints_require_authentication_and_service_config_permission(): void
    {
        $this->getJson('/api/v1/admin/toyota-service/packages')->assertUnauthorized();

        Passport::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/toyota-service/packages')->assertForbidden();
        $this->postJson('/api/v1/admin/toyota-service/packages', $this->payload())
            ->assertForbidden();

        $staff = User::factory()->create();
        $staff->assignRole('staff');
        Passport::actingAs($staff);
        $this->getJson('/api/v1/admin/toyota-service/packages')->assertOk();
        $this->postJson('/api/v1/admin/toyota-service/packages', $this->payload())
            ->assertForbidden();

        Passport::actingAs($this->admin);
        $this->getJson('/api/v1/admin/toyota-service/packages')->assertOk();
    }

    public function test_list_orders_general_packages_first_then_model_and_km(): void
    {
        ToyotaServicePackage::factory()->create(['code' => 'a', 'vehicle_model' => 'Raize', 'km_interval' => 10000]);
        ToyotaServicePackage::factory()->create(['code' => 'b', 'vehicle_model' => null, 'km_interval' => 20000]);
        ToyotaServicePackage::factory()->create(['code' => 'c', 'vehicle_model' => 'Avanza', 'km_interval' => 20000]);
        ToyotaServicePackage::factory()->create(['code' => 'd', 'vehicle_model' => null, 'km_interval' => 10000]);
        ToyotaServicePackage::factory()->create([
            'code' => 'e',
            'vehicle_model' => 'Avanza',
            'km_interval' => 10000,
            'is_active' => false,
        ]);

        Passport::actingAs($this->admin);

        $response = $this->getJson('/api/v1/admin/toyota-service/packages')->assertOk();

        self::assertSame(['d', 'b', 'e', 'c', 'a'], array_column($response->json('data'), 'code'));
        $response
            ->assertJsonPath('data.0.vehicle_model', null)
            ->assertJsonPath('data.0.is_effective', true)
            ->assertJsonPath('data.2.is_effective', false)
            ->assertJsonPath('data.0.total_cost', 1000000);
    }

    public function test_admin_can_create_a_package_with_server_defaults(): void
    {
        Passport::actingAs($this->admin);

        $this->postJson('/api/v1/admin/toyota-service/packages', $this->payload([
            'vehicle_model' => '  Kijang   Innova Zenix ',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.vehicle_model', 'Kijang Innova Zenix')
            ->assertJsonPath('data.km_interval', 10000)
            ->assertJsonPath('data.labor_cost', 350000)
            ->assertJsonPath('data.parts_cost', 650000)
            ->assertJsonPath('data.total_cost', 1000000)
            ->assertJsonPath('data.name', 'Servis Berkala 10.000 km')
            ->assertJsonPath('data.code', 'T-CARE')
            ->assertJsonPath('data.includes', [])
            ->assertJsonPath('data.duration_min_minutes', 60)
            ->assertJsonPath('data.duration_max_minutes', 180)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.effective_from', '2026-10-06')
            ->assertJsonPath('data.effective_to', null)
            ->assertJsonPath('data.source_reference', 'Admin Panel aplikasi TRIVA')
            ->assertJsonPath('data.is_effective', true);
    }

    public function test_the_app_form_payload_is_accepted_as_sent(): void
    {
        Passport::actingAs($this->admin);

        $this->postJson('/api/v1/admin/toyota-service/packages', [
            'vehicle_model' => null,
            'km_interval' => 40000,
            'labor_cost' => 900000,
            'parts_cost' => 1800000,
            'name' => 'Servis Besar 40.000 km',
            'includes' => ['Oli mesin', '', 'Filter udara'],
            'is_active' => false,
        ])
            ->assertCreated()
            ->assertJsonPath('data.vehicle_model', null)
            ->assertJsonPath('data.name', 'Servis Besar 40.000 km')
            ->assertJsonPath('data.includes', ['Oli mesin', 'Filter udara'])
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.is_effective', false);
    }

    public function test_a_duplicate_model_and_km_is_rejected(): void
    {
        ToyotaServicePackage::factory()->create([
            'code' => 'lama',
            'vehicle_model' => 'Avanza',
            'km_interval' => 10000,
        ]);
        ToyotaServicePackage::factory()->create([
            'code' => 'umum',
            'vehicle_model' => null,
            'km_interval' => 20000,
        ]);

        Passport::actingAs($this->admin);

        $this->postJson('/api/v1/admin/toyota-service/packages', $this->payload([
            'vehicle_model' => 'avanza',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['km_interval']);

        $this->postJson('/api/v1/admin/toyota-service/packages', $this->payload([
            'vehicle_model' => null,
            'km_interval' => 20000,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['km_interval']);

        $this->postJson('/api/v1/admin/toyota-service/packages', $this->payload([
            'vehicle_model' => 'Veloz',
        ]))->assertCreated();
    }

    public function test_required_budget_fields_and_ranges_are_validated(): void
    {
        Passport::actingAs($this->admin);

        $this->postJson('/api/v1/admin/toyota-service/packages', [
            'km_interval' => 500,
            'labor_cost' => -1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['km_interval', 'labor_cost', 'parts_cost']);

        $this->postJson('/api/v1/admin/toyota-service/packages', $this->payload([
            'duration_min_minutes' => 120,
            'duration_max_minutes' => 60,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['duration_max_minutes']);
    }

    public function test_patch_updates_budgets_and_excludes_itself_from_the_duplicate_check(): void
    {
        $package = ToyotaServicePackage::factory()->create([
            'code' => 'berkala-10k',
            'name' => 'Paket lama',
            'vehicle_model' => 'Avanza',
            'km_interval' => 10000,
        ]);

        Passport::actingAs($this->admin);

        $this->patchJson('/api/v1/admin/toyota-service/packages/'.$package->getKey(), [
            'vehicle_model' => 'Avanza',
            'km_interval' => 10000,
            'labor_cost' => 400000,
            'parts_cost' => 700000,
            'name' => 'Paket lama',
            'includes' => ['Oli mesin'],
            'is_active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.labor_cost', 400000)
            ->assertJsonPath('data.parts_cost', 700000)
            ->assertJsonPath('data.total_cost', 1100000)
            ->assertJsonPath('data.includes', ['Oli mesin'])
            ->assertJsonPath('data.code', 'berkala-10k')
            ->assertJsonPath('data.source_reference', 'Paket reguler Auto2000 Kertajaya.');
    }

    public function test_patch_with_empty_name_regenerates_the_default_from_the_new_km(): void
    {
        $package = ToyotaServicePackage::factory()->create([
            'code' => 'berkala-10k',
            'name' => 'Paket lama',
            'vehicle_model' => 'Avanza',
            'km_interval' => 10000,
        ]);

        Passport::actingAs($this->admin);

        $this->patchJson('/api/v1/admin/toyota-service/packages/'.$package->getKey(), [
            'vehicle_model' => null,
            'km_interval' => 20000,
            'labor_cost' => 450000,
            'parts_cost' => 900000,
            'name' => null,
            'code' => null,
            'source_reference' => null,
            'includes' => [],
            'is_active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Servis Berkala 20.000 km')
            ->assertJsonPath('data.vehicle_model', null)
            ->assertJsonPath('data.code', 'berkala-10k')
            ->assertJsonPath('data.source_reference', 'Paket reguler Auto2000 Kertajaya.')
            ->assertJsonPath('data.includes', []);
    }

    public function test_a_default_name_follows_a_changed_km_even_when_sent_back(): void
    {
        $package = ToyotaServicePackage::factory()->create([
            'code' => 'T-CARE',
            'name' => 'Servis Berkala 10.000 km',
            'km_interval' => 10000,
        ]);

        Passport::actingAs($this->admin);

        $this->patchJson('/api/v1/admin/toyota-service/packages/'.$package->getKey(), [
            'km_interval' => 30000,
            'name' => 'Servis Berkala 10.000 km',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Servis Berkala 30.000 km');
    }

    public function test_patch_cannot_collide_with_another_package(): void
    {
        ToyotaServicePackage::factory()->create([
            'code' => 'a',
            'vehicle_model' => 'Raize',
            'km_interval' => 20000,
        ]);
        $package = ToyotaServicePackage::factory()->create([
            'code' => 'b',
            'vehicle_model' => 'Raize',
            'km_interval' => 10000,
        ]);

        Passport::actingAs($this->admin);

        $this->patchJson('/api/v1/admin/toyota-service/packages/'.$package->getKey(), [
            'km_interval' => 20000,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['km_interval']);
    }

    public function test_admin_can_delete_a_package_but_staff_cannot(): void
    {
        $package = ToyotaServicePackage::factory()->create();
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        Passport::actingAs($staff);
        $this->deleteJson('/api/v1/admin/toyota-service/packages/'.$package->getKey())
            ->assertForbidden();

        Passport::actingAs($this->admin);
        $this->deleteJson('/api/v1/admin/toyota-service/packages/'.$package->getKey())
            ->assertOk();

        self::assertModelMissing($package);
    }

    public function test_a_package_saved_by_admin_drives_the_maintenance_estimate(): void
    {
        Passport::actingAs($this->admin);

        $this->postJson('/api/v1/admin/toyota-service/packages', $this->payload([
            'vehicle_model' => 'Raize',
            'km_interval' => 20000,
            'labor_cost' => 500000,
            'parts_cost' => 800000,
        ]))->assertCreated();

        $this->getJson('/api/v1/toyota-service/maintenance-estimate?vehicle_model=raize&mileage=15000')
            ->assertOk()
            ->assertJsonPath('data.recommended.km_interval', 20000)
            ->assertJsonPath('data.recommended.labor_cost', 500000)
            ->assertJsonPath('data.recommended.parts_cost', 800000)
            ->assertJsonPath('data.available_models', ['Raize']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'vehicle_model' => 'Avanza',
            'km_interval' => 10000,
            'labor_cost' => 350000,
            'parts_cost' => 650000,
            ...$overrides,
        ];
    }
}
