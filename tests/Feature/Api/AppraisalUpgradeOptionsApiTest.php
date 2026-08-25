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

    public function test_it_offers_the_two_closest_units_a_step_above_the_appraised_car(): void
    {
        $appraisal = $this->appraisalWorth(120_000_000);
        Passport::actingAs($this->customer);

        $response = $this->getJson(
            "/api/v1/appraisals/{$appraisal->id}/upgrade-options",
        )->assertOk();

        self::assertSame(120_000_000, $response->json('data.trade_in_value'));
        $options = $response->json('data.options');
        self::assertCount(2, $options);
        // Termurah dulu: satu tingkat di atas, bukan lompat kelas.
        self::assertSame('UP-200', $options[0]['program_code']);
        self::assertSame('UP-300', $options[1]['program_code']);
        foreach ($options as $option) {
            self::assertGreaterThan(0, $option['monthly_installment']);
            self::assertSame(
                120_000_000,
                $option['down_payment_from_appraisal'],
            );
        }
    }

    public function test_units_whose_minimum_down_payment_exceeds_the_appraisal_are_skipped(): void
    {
        // Harga appraisal hanya 30 juta: DP minimum 20% dari unit 200 juta
        // adalah 40 juta, jadi unit itu tidak boleh ditawarkan.
        $appraisal = $this->appraisalWorth(30_000_000);
        Passport::actingAs($this->customer);

        $options = $this->getJson(
            "/api/v1/appraisals/{$appraisal->id}/upgrade-options",
        )->assertOk()->json('data.options');

        self::assertSame([], $options);
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
        foreach ([200_000_000, 300_000_000, 400_000_000] as $index => $otr) {
            CreditProgram::factory()->create([
                'program_code' => 'UP-'.($otr / 1_000_000),
                'city' => 'Surabaya',
                'otr_price' => $otr,
                'approved_discount' => 0,
                'minimum_dp_basis_points' => 2000,
                'maximum_dp_basis_points' => 8000,
                'vehicle_model' => 'Model '.$index,
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
