<?php

namespace Tests\Feature\Api;

use App\Models\ToyotaSscCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Passport\Passport;
use Tests\TestCase;

class VehicleBenefitCheckApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-25 03:00:00', 'UTC'));
        Passport::actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_requires_authentication_and_a_chassis_number(): void
    {
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/vehicle-benefits/check', ['vin' => 'MHKA1234'])
            ->assertUnauthorized();

        Passport::actingAs(User::factory()->create());
        $this->postJson('/api/v1/vehicle-benefits/check', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vin']);
    }

    public function test_without_campaign_data_it_says_so_instead_of_guessing(): void
    {
        $this->postJson('/api/v1/vehicle-benefits/check', [
            'vin' => 'MHKA1AA1JNK123456',
            'year' => 2024,
        ])
            ->assertOk()
            ->assertJsonPath('data.ssc.status', 'unverified')
            ->assertJsonPath('data.t_care.status', 'active');
    }

    public function test_a_covered_chassis_number_is_reported_with_its_campaign(): void
    {
        $this->campaign(['vin_prefixes' => ['MHKA1AA1']]);

        $this->postJson('/api/v1/vehicle-benefits/check', [
            'vin' => 'mhka1aa1-jnk 123456',
            'year' => 2022,
        ])
            ->assertOk()
            ->assertJsonPath('data.vin', 'MHKA1AA1JNK123456')
            ->assertJsonPath('data.ssc.status', 'affected')
            ->assertJsonPath('data.ssc.campaigns.0.campaign_code', 'SSC-2026-01')
            ->assertJsonPath('data.recommendation.channel', 'toyota_service');
    }

    public function test_a_chassis_number_outside_the_campaign_is_cleared(): void
    {
        $this->campaign(['vin_prefixes' => ['MHKA1AA1']]);

        $this->postJson('/api/v1/vehicle-benefits/check', [
            'vin' => 'MHFB9999XXX000111',
            'year' => 2022,
        ])
            ->assertOk()
            ->assertJsonPath('data.ssc.status', 'not_affected');
    }

    public function test_a_year_outside_the_campaign_window_is_not_matched(): void
    {
        $this->campaign([
            'vin_prefixes' => ['MHKA1AA1'],
            'year_from' => 2021,
            'year_to' => 2022,
        ]);

        $this->postJson('/api/v1/vehicle-benefits/check', [
            'vin' => 'MHKA1AA1JNK123456',
            'year' => 2024,
        ])
            ->assertOk()
            ->assertJsonPath('data.ssc.status', 'not_affected');
    }

    public function test_an_active_t_care_steers_the_customer_to_auto2000(): void
    {
        // Cakupan empat tahun sejak 2024 masih berjalan pada Agustus 2026.
        $response = $this->postJson('/api/v1/vehicle-benefits/check', [
            'vin' => 'MHKA1AA1JNK123456',
            'year' => 2024,
        ])->assertOk();

        $response
            ->assertJsonPath('data.t_care.status', 'active')
            ->assertJsonPath('data.t_care.expires_on', '2028-01-01')
            ->assertJsonPath('data.recommendation.channel', 'toyota_service');
        self::assertGreaterThan(0, $response->json('data.t_care.months_remaining'));
    }

    public function test_an_expired_t_care_points_the_customer_to_otoxpert(): void
    {
        $this->postJson('/api/v1/vehicle-benefits/check', [
            'vin' => 'MHKA1AA1JNK123456',
            'year' => 2015,
        ])
            ->assertOk()
            ->assertJsonPath('data.t_care.status', 'expired')
            ->assertJsonPath('data.t_care.months_remaining', 0)
            ->assertJsonPath('data.recommendation.channel', 'otoxpert');
    }

    public function test_a_missing_year_asks_for_it_rather_than_assuming(): void
    {
        $this->postJson('/api/v1/vehicle-benefits/check', [
            'vin' => 'MHKA1AA1JNK123456',
        ])
            ->assertOk()
            ->assertJsonPath('data.t_care.status', 'unknown')
            ->assertJsonPath('data.t_care.months_remaining', null);
    }

    /** @param array<string, mixed> $overrides */
    private function campaign(array $overrides = []): ToyotaSscCampaign
    {
        return ToyotaSscCampaign::query()->create([
            'campaign_code' => 'SSC-2026-01',
            'title' => 'Penggantian fuel pump',
            'description' => 'Penggantian fuel pump tanpa biaya di bengkel resmi Toyota.',
            'vehicle_model' => 'Avanza',
            'year_from' => 2020,
            'year_to' => 2023,
            'recommended_action' => 'Booking servis di Auto2000 Kertajaya.',
            'is_active' => true,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'source_reference' => 'Surat edaran TAM 2026.',
            ...$overrides,
        ]);
    }
}
