<?php

namespace Tests\Feature\Api;

use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VehicleMakeSeeder;
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
        ]);
    }

    public function test_vehicle_make_master_requires_authentication(): void
    {
        $this->getJson('/api/v1/vehicle-makes')->assertUnauthorized();
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

        $makeId = $this->getJson('/api/v1/vehicle-makes')
            ->json('data.27.id');

        $payload = [
            'make_id' => $makeId,
            'model' => 'Avanza',
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
            ->assertJsonPath('data.city', 'KOTA SURABAYA')
            ->assertJsonPath('data.province_id', $province->getKey())
            ->assertJsonPath('data.city_id', $city->getKey());

        $this->postJson('/api/v1/vehicles', [
            ...$payload,
            'province_id' => $otherProvince->getKey(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['city_id']);
    }
}
