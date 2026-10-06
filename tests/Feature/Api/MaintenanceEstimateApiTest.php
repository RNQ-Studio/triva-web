<?php

namespace Tests\Feature\Api;

use App\Models\ToyotaServicePackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class MaintenanceEstimateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Passport::actingAs(User::factory()->create());
    }

    public function test_it_recommends_the_next_service_interval_for_the_mileage(): void
    {
        $this->package(km: 10000, parts: 650000, labor: 350000);
        $this->package(km: 20000, parts: 900000, labor: 450000);
        $this->package(km: 40000, parts: 1800000, labor: 900000);

        $response = $this->getJson(
            '/api/v1/toyota-service/maintenance-estimate?mileage=23000',
        )->assertOk();

        $response
            ->assertJsonPath('data.recommended.km_interval', 40000)
            ->assertJsonPath('data.recommended.total_cost', 2700000)
            ->assertJsonCount(3, 'data.packages');
    }

    public function test_a_model_specific_package_replaces_the_general_one(): void
    {
        $this->package(km: 10000, parts: 650000, labor: 350000);
        $this->package(
            km: 10000,
            parts: 1200000,
            labor: 600000,
            model: 'Kijang Innova Zenix',
        );

        $this->getJson(
            '/api/v1/toyota-service/maintenance-estimate'
            .'?mileage=9000&vehicle_model=Kijang Innova Zenix',
        )
            ->assertOk()
            ->assertJsonPath('data.recommended.total_cost', 1800000)
            ->assertJsonPath('data.recommended.vehicle_model', 'Kijang Innova Zenix')
            ->assertJsonCount(1, 'data.packages');
    }

    public function test_mileage_beyond_every_package_falls_back_to_the_largest(): void
    {
        $this->package(km: 10000, parts: 650000, labor: 350000);
        $this->package(km: 20000, parts: 900000, labor: 450000);

        $this->getJson(
            '/api/v1/toyota-service/maintenance-estimate?mileage=250000',
        )
            ->assertOk()
            ->assertJsonPath('data.recommended.km_interval', 20000);
    }

    public function test_without_package_data_no_estimate_is_invented(): void
    {
        $this->getJson('/api/v1/toyota-service/maintenance-estimate?mileage=15000')
            ->assertOk()
            ->assertJsonPath('data.recommended', null)
            ->assertJsonPath('data.packages', []);
    }

    public function test_available_models_list_effective_model_specific_packages(): void
    {
        $this->package(km: 10000, parts: 650000, labor: 350000);
        $this->package(km: 10000, parts: 700000, labor: 400000, model: 'raize');
        $this->package(km: 20000, parts: 900000, labor: 450000, model: 'Avanza');
        $this->package(km: 30000, parts: 900000, labor: 450000, model: 'Raize');
        $this->package(km: 40000, parts: 900000, labor: 450000, model: 'Avanza');
        ToyotaServicePackage::factory()->create([
            'code' => 'nonaktif',
            'vehicle_model' => 'Calya',
            'is_active' => false,
        ]);
        ToyotaServicePackage::factory()->create([
            'code' => 'kedaluwarsa',
            'vehicle_model' => 'Agya',
            'effective_from' => '2020-01-01',
            'effective_to' => '2020-12-31',
        ]);

        $this->getJson('/api/v1/toyota-service/maintenance-estimate')
            ->assertOk()
            ->assertJsonPath('data.available_models', ['Avanza', 'Raize']);
    }

    public function test_the_vehicle_model_is_trimmed_before_matching(): void
    {
        $this->package(km: 10000, parts: 650000, labor: 350000);
        $this->package(km: 10000, parts: 1200000, labor: 600000, model: 'Avanza');

        $this->getJson(
            '/api/v1/toyota-service/maintenance-estimate'
            .'?mileage=9000&vehicle_model='.urlencode('  Avanza  '),
        )
            ->assertOk()
            ->assertJsonPath('data.vehicle_model', 'Avanza')
            ->assertJsonPath('data.recommended.vehicle_model', 'Avanza');
    }

    public function test_an_implausible_mileage_is_rejected(): void
    {
        $this->getJson('/api/v1/toyota-service/maintenance-estimate?mileage=-5')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mileage']);
    }

    private function package(
        int $km,
        int $parts,
        int $labor,
        ?string $model = null,
    ): ToyotaServicePackage {
        return ToyotaServicePackage::factory()->create([
            'code' => 'berkala-'.$km,
            'name' => 'Servis berkala '.number_format($km, 0, ',', '.').' km',
            'vehicle_model' => $model,
            'km_interval' => $km,
            'parts_cost' => $parts,
            'labor_cost' => $labor,
        ]);
    }
}
