<?php

namespace Tests\Feature\Api;

use App\Models\Region;
use App\Models\User;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VehicleMakeSeeder;
use Database\Seeders\VehicleModelSeeder;
use Database\Seeders\VehicleVariantSeeder;
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
            VehicleVariantSeeder::class,
        ]);
    }

    public function test_vehicle_make_master_requires_authentication(): void
    {
        $this->getJson('/api/v1/vehicle-makes')->assertUnauthorized();
        $this->getJson('/api/v1/vehicle-makes/1/models')->assertUnauthorized();
        $this->getJson('/api/v1/vehicle-models/1/variants')
            ->assertUnauthorized();
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

    public function test_customer_can_list_active_variants_for_selected_model(): void
    {
        Passport::actingAs(User::factory()->create());
        $avanza = VehicleModel::query()
            ->whereHas(
                'vehicleMake',
                fn ($query) => $query->where('slug', 'toyota'),
            )
            ->where('name', 'Avanza')
            ->firstOrFail();

        $response = $this->getJson(
            "/api/v1/vehicle-models/{$avanza->id}/variants",
        )
            ->assertOk()
            ->assertJsonFragment([
                'model_id' => $avanza->id,
                'name' => '1.5 G CVT TSS',
                'year_from' => 1950,
                'year_to' => null,
                'transmission' => 'automatic',
                'fuel_type' => 'gasoline',
            ])
            ->assertJsonFragment(['name' => '1.3 E CVT']);

        $this->getJson(
            "/api/v1/vehicle-models/{$avanza->id}/variants?year=2022",
        )
            ->assertOk()
            ->assertJsonCount(count($response->json('data')), 'data');

        $inactive = VehicleVariant::query()
            ->where('vehicle_model_id', $avanza->id)
            ->where('name', '1.5 G CVT')
            ->firstOrFail();
        $inactive->update(['is_active' => false]);

        $this->getJson("/api/v1/vehicle-models/{$avanza->id}/variants")
            ->assertOk()
            ->assertJsonMissing(['id' => $inactive->id]);

        $avanza->update(['is_active' => false]);
        $this->getJson("/api/v1/vehicle-models/{$avanza->id}/variants")
            ->assertNotFound();
    }

    public function test_calya_exposes_four_model_scoped_master_variants(): void
    {
        Passport::actingAs(User::factory()->create());
        $calya = VehicleModel::query()
            ->whereHas(
                'vehicleMake',
                fn ($query) => $query->where('slug', 'toyota'),
            )
            ->where('name', 'Calya')
            ->firstOrFail();

        $this->getJson("/api/v1/vehicle-models/{$calya->id}/variants")
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.name', '1.2 E MT STD')
            ->assertJsonPath('data.1.name', '1.2 E MT')
            ->assertJsonPath('data.2.name', '1.2 G MT')
            ->assertJsonPath('data.3.name', '1.2 G AT');
    }

    public function test_seeded_2026_catalog_covers_honda_daihatsu_and_suzuki(): void
    {
        Passport::actingAs(User::factory()->create());

        $expectedCatalog = [
            'honda' => [
                'model_count' => 13,
                'variant_count' => 29,
                'sample_model' => 'HRV',
                'sample_variant' => 'RS e:HEV',
                'fuel_type' => 'hybrid',
            ],
            'daihatsu' => [
                'model_count' => 10,
                'variant_count' => 64,
                'sample_model' => 'Rocky Hybrid',
                'sample_variant' => '1.2L e-SMART Hybrid',
                'fuel_type' => 'hybrid',
            ],
            'suzuki' => [
                'model_count' => 11,
                'variant_count' => 46,
                'sample_model' => 'e-Vitara',
                'sample_variant' => 'GLX Single Tone',
                'fuel_type' => 'electric',
            ],
        ];

        foreach ($expectedCatalog as $makeSlug => $expected) {
            $make = VehicleMake::query()
                ->where('slug', $makeSlug)
                ->firstOrFail();
            $variants = VehicleVariant::query()
                ->whereHas(
                    'vehicleModel',
                    fn ($query) => $query
                        ->where('vehicle_make_id', $make->id)
                        ->where('is_active', true),
                )
                ->where('is_active', true);

            $this->assertSame(
                $expected['model_count'],
                (clone $variants)->distinct()->count('vehicle_model_id'),
            );
            $this->assertSame(
                $expected['variant_count'],
                (clone $variants)->count(),
            );
            $this->assertSame(
                $expected['variant_count'],
                (clone $variants)
                    ->whereNotNull('source_url')
                    ->whereDate('source_checked_at', '2026-07-28')
                    ->count(),
            );

            $sampleModel = VehicleModel::query()
                ->where('vehicle_make_id', $make->id)
                ->where('name', $expected['sample_model'])
                ->firstOrFail();

            $this->getJson(
                "/api/v1/vehicle-models/{$sampleModel->id}/variants",
            )
                ->assertOk()
                ->assertJsonFragment([
                    'model_id' => $sampleModel->id,
                    'name' => $expected['sample_variant'],
                    'transmission' => 'automatic',
                    'fuel_type' => $expected['fuel_type'],
                ]);
        }

        $this->seed(VehicleVariantSeeder::class);

        $this->assertSame(
            196,
            VehicleVariant::query()->count(),
            'VehicleVariantSeeder must remain idempotent.',
        );
        $this->assertSame(
            0,
            VehicleVariant::query()
                ->where('year_from', '!=', 1950)
                ->orWhereNotNull('year_to')
                ->count(),
            'Legacy year fields must not constrain model-scoped variants.',
        );
    }

    public function test_vehicle_can_use_canonical_variant_or_manual_fallback(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        $toyota = VehicleMake::query()->where('slug', 'toyota')->firstOrFail();
        $avanza = VehicleModel::query()
            ->where('vehicle_make_id', $toyota->id)
            ->where('name', 'Avanza')
            ->firstOrFail();
        $variant = VehicleVariant::query()
            ->where('vehicle_model_id', $avanza->id)
            ->where('name', '1.5 G CVT')
            ->firstOrFail();

        $payload = [
            'make_id' => $toyota->id,
            'model_id' => $avanza->id,
            'variant_id' => $variant->id,
            'year' => 2000,
            'transmission' => 'automatic',
            'fuel_type' => 'gasoline',
            'mileage' => 1200,
            'color' => 'Putih',
            'license_plate' => 'L 2026 TRV',
            'city' => 'Surabaya',
        ];

        $this->postJson('/api/v1/vehicles', $payload)
            ->assertCreated()
            ->assertJsonPath('data.variant_id', $variant->id)
            ->assertJsonPath('data.variant', '1.5 G CVT');

        $this->postJson('/api/v1/vehicles', [
            ...$payload,
            'variant_id' => null,
            'variant' => 'Varian Karoseri Khusus',
            'license_plate' => 'L 2027 TRV',
        ])
            ->assertCreated()
            ->assertJsonPath('data.variant_id', null)
            ->assertJsonPath('data.variant', 'Varian Karoseri Khusus');
    }

    public function test_vehicle_rejects_variant_outside_selected_model_or_metadata(): void
    {
        Passport::actingAs(User::factory()->create());
        $toyota = VehicleMake::query()->where('slug', 'toyota')->firstOrFail();
        $avanza = VehicleModel::query()
            ->where('vehicle_make_id', $toyota->id)
            ->where('name', 'Avanza')
            ->firstOrFail();
        $calya = VehicleModel::query()
            ->where('vehicle_make_id', $toyota->id)
            ->where('name', 'Calya')
            ->firstOrFail();
        $variant = VehicleVariant::query()
            ->where('vehicle_model_id', $avanza->id)
            ->where('name', '1.5 G CVT')
            ->firstOrFail();
        $payload = [
            'make_id' => $toyota->id,
            'model_id' => $avanza->id,
            'variant_id' => $variant->id,
            'year' => 2026,
            'transmission' => 'automatic',
            'fuel_type' => 'gasoline',
            'mileage' => 1200,
            'color' => 'Putih',
            'license_plate' => 'L 2026 TRV',
            'city' => 'Surabaya',
        ];

        $this->postJson('/api/v1/vehicles', [
            ...$payload,
            'model_id' => $calya->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['variant_id']);

        $this->postJson('/api/v1/vehicles', [
            ...$payload,
            'transmission' => 'manual',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['transmission']);
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
