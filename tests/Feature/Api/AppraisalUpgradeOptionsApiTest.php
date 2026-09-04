<?php

namespace Tests\Feature\Api;

use App\Models\Appraisal;
use App\Models\CreditProgram;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Enums\AppraisalConfidence;
use App\Support\Enums\AppraisalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AppraisalUpgradeOptionsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create();
    }

    public function test_it_offers_veloz_hybrid_and_zenix_hybrid_above_150_million(): void
    {
        $appraisal = $this->appraisalWorth(176_000_000);
        Passport::actingAs($this->customer);

        $response = $this->getJson(
            "/api/v1/appraisals/{$appraisal->id}/upgrade-options",
        )->assertOk();

        self::assertSame(176_000_000, $response->json('data.trade_in_value'));
        $options = $response->json('data.options');
        self::assertCount(2, $options);
        // Harga bawah dulu, lalu harga atas.
        self::assertSame('veloz_hybrid', $options[0]['unit_key']);
        self::assertSame('zenix_hybrid', $options[1]['unit_key']);
        self::assertStringEndsWith('/storage/credit-units/veloz.jpg', $options[0]['image_url']);
        foreach ($options as $option) {
            self::assertSame(176_000_000, $option['down_payment_from_appraisal']);
            self::assertSame(60, $option['tenor_months']);
            self::assertGreaterThan(0, $option['monthly_installment']);
            self::assertGreaterThan(0, $option['annual_flat_rate_basis_points']);
        }
    }

    public function test_it_offers_veloz_hybrid_and_reborn_between_100_and_150_million(): void
    {
        $appraisal = $this->appraisalWorth(120_000_000);
        Passport::actingAs($this->customer);

        $options = $this->getJson(
            "/api/v1/appraisals/{$appraisal->id}/upgrade-options",
        )->assertOk()->json('data.options');

        self::assertSame(['veloz_hybrid', 'innova_reborn'], array_column($options, 'unit_key'));
    }

    public function test_it_offers_raize_and_veloz_hybrid_below_100_million(): void
    {
        $appraisal = $this->appraisalWorth(60_000_000);
        Passport::actingAs($this->customer);

        $options = $this->getJson(
            "/api/v1/appraisals/{$appraisal->id}/upgrade-options",
        )->assertOk()->json('data.options');

        self::assertSame(['raize', 'veloz_hybrid'], array_column($options, 'unit_key'));
        self::assertSame(60_000_000, $options[0]['down_payment_from_appraisal']);
    }

    public function test_units_not_configured_by_the_branch_are_skipped(): void
    {
        $appraisal = $this->appraisalWorth(120_000_000);
        CreditProgram::query()->where('unit_key', 'innova_reborn')->delete();
        Passport::actingAs($this->customer);

        $options = $this->getJson(
            "/api/v1/appraisals/{$appraisal->id}/upgrade-options",
        )->assertOk()->json('data.options');

        self::assertSame(['veloz_hybrid'], array_column($options, 'unit_key'));
    }

    public function test_an_appraisal_without_a_result_returns_no_options(): void
    {
        $appraisal = Appraisal::factory()->create([
            'user_id' => $this->customer->getKey(),
            'status' => AppraisalStatus::Draft,
        ]);
        Passport::actingAs($this->customer);

        $this->getJson("/api/v1/appraisals/{$appraisal->id}/upgrade-options")
            ->assertOk()
            ->assertJsonPath('data.trade_in_value', 0)
            ->assertJsonPath('data.options', []);
    }

    public function test_another_customers_appraisal_is_not_readable(): void
    {
        $appraisal = $this->appraisalWorth(120_000_000);
        Passport::actingAs(User::factory()->create());

        $this->getJson("/api/v1/appraisals/{$appraisal->id}/upgrade-options")
            ->assertForbidden();
    }

    private function appraisalWorth(int $tradeInHigh): Appraisal
    {
        CreditProgram::query()->delete();
        $units = [
            ['veloz_hybrid', 'Veloz Hybrid', 405_000_000, 'credit-units/veloz.jpg'],
            ['zenix_hybrid', 'Kijang Innova Zenix Hybrid', 490_000_000, null],
            ['innova_reborn', 'Kijang Innova Reborn', 425_000_000, null],
            ['raize', 'Raize', 275_000_000, null],
        ];
        foreach ($units as [$key, $model, $otr, $image]) {
            CreditProgram::factory()->create([
                'program_code' => 'UNIT-'.strtoupper($key),
                'city' => 'Surabaya',
                'unit_key' => $key,
                'image_path' => $image,
                'vehicle_model' => $model,
                'otr_price' => $otr,
                'approved_discount' => 0,
            ]);
        }

        $vehicle = Vehicle::factory()->create([
            'user_id' => $this->customer->getKey(),
            'city' => 'Surabaya',
        ]);
        $appraisal = Appraisal::factory()->create([
            'vehicle_id' => $vehicle->getKey(),
            'user_id' => $this->customer->getKey(),
            'status' => AppraisalStatus::ResultReady,
        ]);
        $appraisal->results()->create([
            'version' => 1,
            'market_low' => $tradeInHigh + 20_000_000,
            'market_mid' => $tradeInHigh + 30_000_000,
            'market_high' => $tradeInHigh + 40_000_000,
            'trade_in_low' => (int) ($tradeInHigh * 0.9),
            'trade_in_high' => $tradeInHigh,
            'confidence' => AppraisalConfidence::High,
            'comparable_count' => 6,
            'data_as_of' => now()->subDay(),
            'valid_until' => now()->addDays(7),
            'requires_physical_inspection' => true,
            'disclaimer' => 'Perlu inspeksi fisik.',
            'published_at' => now(),
        ]);

        return $appraisal;
    }
}
