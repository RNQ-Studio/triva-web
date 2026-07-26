<?php

namespace Tests\Feature\Api;

use App\Models\Region;
use App\Models\User;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VehicleMakeSeeder;
use Database\Seeders\VehicleModelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class VehicleMakeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            VehicleMakeSeeder::class,
            VehicleModelSeeder::class,
        ]);
    }

    public function test_vehicle_make_master_requires_authentication(): void
    {
        $this->getJson('/api/v1/vehicle-makes')->assertUnauthorized();
        $this->getJson('/api/v1/vehicle-makes/1/models')->assertUnauthorized();
    }

    public function test_customer_can_list_active_vehicle_makes_with_local_logos(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->getJson('/api/v1/vehicle-makes')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'AION')
            ->assertJsonPath(
                'data.0.logo_url',
                url('images/vehicle-makes/aion.png'),
            )
            ->assertJsonCount(33, 'data');
    }

    public function test_customer_can_list_active_models_for_selected_make(): void
    {
        Passport::actingAs(User::factory()->create());
        $toyota = VehicleMake::query()->where('slug', 'toyota')->firstOrFail();

        $this->getJson("/api/v1/vehicle-makes/{$toyota->id}/models")
            ->assertOk()
            ->assertJsonFragment([
                'make_id' => $toyota->id,
                'name' => 'Avanza',
            ])
            ->assertJsonMissing(['name' => 'Jazz']);

        $inactive = VehicleModel::query()
            ->where('vehicle_make_id', $toyota->id)
            ->where('name', 'Calya')
            ->firstOrFail();
        $inactive->update(['is_active' => false]);

        $this->getJson("/api/v1/vehicle-makes/{$toyota->id}/models")
            ->assertOk()
            ->assertJsonMissing(['name' => 'Calya']);
    }

    public function test_vehicle_uses_canonical_make_and_hierarchical_city_master(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $country = Region::query()->create([
            'type' => 'country',
            'code' => 'ID',
            'name' => 'INDONESIA',
        ]);
        $province = Region::query()->create([
            'parent_id' => $country->getKey(),
            'type' => 'state',
            'code' => '35',
            'name' => 'JAWA TIMUR',
        ]);
        $city = Region::query()->create([
            'parent_id' => $province->getKey(),
            'type' => 'city',
            'code' => '3578',
            'name' => 'KOTA SURABAYA',
        ]);
        $otherProvince = Region::query()->create([
            'parent_id' => $country->getKey(),
            'type' => 'state',
            'code' => '31',
            'name' => 'DKI JAKARTA',
        ]);

        $toyota = VehicleMake::query()->where('slug', 'toyota')->firstOrFail();
        $avanza = VehicleModel::query()
            ->where('vehicle_make_id', $toyota->id)
            ->where('name', 'Avanza')
            ->firstOrFail();
        $brio = VehicleModel::query()
            ->whereHas(
                'vehicleMake',
                fn ($query) => $query->where('slug', 'honda'),
            )
            ->where('name', 'Brio')
            ->firstOrFail();

        $payload = [
            'make_id' => $toyota->id,
            'model_id' => $avanza->id,
            'variant' => '1.5 G',
            'year' => 2022,
            'transmission' => 'automatic',
            'fuel_type' => 'gasoline',
            'mileage' => 42500,
            'color' => 'Putih',
            'license_plate' => 'L 1234 TRV',
            'province_id' => $province->getKey(),
            'city_id' => $city->getKey(),
        ];

        $this->postJson('/api/v1/vehicles', $payload)
            ->assertCreated()
            ->assertJsonPath('data.make', 'Toyota')
            ->assertJsonPath('data.model_id', $avanza->id)
            ->assertJsonPath('data.model', 'Avanza')
            ->assertJsonPath('data.city', 'KOTA SURABAYA')
            ->assertJsonPath('data.province_id', $province->getKey())
            ->assertJsonPath('data.city_id', $city->getKey());

        $this->postJson('/api/v1/vehicles', [
            ...$payload,
            'province_id' => $otherProvince->getKey(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['city_id']);

        $this->postJson('/api/v1/vehicles', [
            ...$payload,
            'model_id' => $brio->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['model_id']);
    }
}
