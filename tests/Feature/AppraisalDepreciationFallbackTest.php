<?php

namespace Tests\Feature;

use App\Models\Appraisal;
use App\Models\CreditProgram;
use App\Models\MarketDataSource;
use App\Models\Vehicle;
use App\Services\AppraisalMarketDataService;
use App\Support\Enums\AppraisalConfidence;
use App\Support\Enums\AppraisalMarketEstimateStatus;
use App\Support\Enums\AppraisalStatus;
use App\Support\Enums\MarketDataSourceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pertanyaan Bp. Iyan pada 20 Agustus 2026: bagaimana bila unitnya belum
 * pernah ada yang menjual, misalnya mobil listrik? Jawabannya memakai data
 * depresiasi supaya harga tetap muncul.
 */
class AppraisalDepreciationFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-25 03:00:00', 'UTC'));
        config(['appraisal.ai.enabled' => false]);
        MarketDataSource::query()
            ->where('code', 'olx_approved_html')
            ->update([
                'status' => MarketDataSourceStatus::Active,
                'approval_reference' => 'OLX-TRIVA-TEST-2026',
                'approved_at' => now()->subDay(),
                'approval_expires_at' => now()->addYear(),
            ]);
        // Pasar bekas kosong: tidak ada satu pun listing yang cocok.
        Http::fake([
            'https://www.olx.co.id/*' => Http::response(
                '<html><body>Tidak ada hasil</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_prices_an_unsold_model_from_the_new_vehicle_price(): void
    {
        $this->newVehicleProgram(otrPrice: 500_000_000);
        $appraisal = $this->appraisal(year: 2024);

        $estimate = app(AppraisalMarketDataService::class)->process($appraisal);

        self::assertSame(AppraisalMarketEstimateStatus::Ready, $estimate->status);
        self::assertSame(AppraisalConfidence::Low, $estimate->confidence);
        self::assertSame(
            'depreciation_fallback_v1',
            data_get($estimate->calculation, 'algorithm'),
        );
        self::assertSame(
            500_000_000,
            data_get($estimate->calculation, 'new_vehicle_price'),
        );
        // Dua tahun: turun 18% lalu 10%, menyisakan 73.8% dari harga baru.
        self::assertSame(369_000_000, $estimate->market_mid);
        self::assertLessThan($estimate->market_mid, $estimate->trade_in_high);
        self::assertSame(AppraisalStatus::ResultReady, $appraisal->refresh()->status);
    }

    public function test_the_published_result_explains_where_the_price_came_from(): void
    {
        $this->newVehicleProgram(otrPrice: 500_000_000);
        $appraisal = $this->appraisal(year: 2024);

        app(AppraisalMarketDataService::class)->process($appraisal);

        $result = $appraisal->refresh()->results()->firstOrFail();
        self::assertStringContainsString('depresiasi', $result->disclaimer);
        self::assertSame(0, $result->comparable_count);
    }

    public function test_an_older_unit_never_drops_below_the_retained_floor(): void
    {
        $this->newVehicleProgram(otrPrice: 500_000_000);
        $appraisal = $this->appraisal(year: 1995);

        $estimate = app(AppraisalMarketDataService::class)->process($appraisal);

        self::assertSame(75_000_000, $estimate->market_mid);
    }

    public function test_without_a_new_vehicle_price_no_number_is_invented(): void
    {
        $appraisal = $this->appraisal(year: 2024);

        $estimate = app(AppraisalMarketDataService::class)->process($appraisal);

        self::assertSame(
            AppraisalMarketEstimateStatus::Insufficient,
            $estimate->status,
        );
        self::assertSame(
            'no_new_vehicle_price',
            data_get($estimate->calculation, 'depreciation_fallback.status'),
        );
        self::assertSame(AppraisalStatus::Failed, $appraisal->refresh()->status);
    }

    private function newVehicleProgram(int $otrPrice): CreditProgram
    {
        return CreditProgram::factory()->create([
            'program_code' => 'EV-2026',
            'vehicle_model' => 'bZ4X',
            'vehicle_variant' => 'Signature',
            'otr_price' => $otrPrice,
            'approved_discount' => 0,
        ]);
    }

    private function appraisal(int $year): Appraisal
    {
        $vehicle = Vehicle::factory()->create([
            'make' => 'Toyota',
            'model' => 'bZ4X',
            'variant' => 'Signature',
            'year' => $year,
            'fuel_type' => 'electric',
        ]);

        return Appraisal::factory()->create([
            'vehicle_id' => $vehicle->getKey(),
            'user_id' => $vehicle->user_id,
            'status' => AppraisalStatus::CollectingMarketData,
            'submitted_at' => now(),
            'tax_status' => 'active',
            'flood_history' => 'no',
            'major_accident_history' => 'no',
            'service_history' => 'complete',
            'ownership' => 'first',
            'condition_grade' => 'a',
            'engine_condition' => 'normal',
            'tyre_condition' => 'normal',
        ]);
    }
}
