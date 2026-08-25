<?php

namespace Tests\Unit\Services;

use App\Models\Appraisal;
use App\Models\Vehicle;
use App\Services\AppraisalValuationEngine;
use App\Support\Enums\AppraisalConfidence;
use App\Support\Enums\AppraisalMarketEstimateStatus;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AppraisalValuationEngineTest extends TestCase
{
    public function test_it_deduplicates_filters_outliers_and_calculates_weighted_range(): void
    {
        $vehicle = new Vehicle([
            'make' => 'Toyota',
            'model' => 'Avanza',
            'variant' => '1.5 G',
            'year' => 2022,
            'transmission' => 'automatic',
            'fuel_type' => 'gasoline',
            'mileage' => 42_500,
            'city' => 'Surabaya',
        ]);
        $appraisal = new Appraisal([
            'tax_status' => 'active',
            'flood_history' => 'no',
            'major_accident_history' => 'no',
            'service_history' => 'complete',
            'ownership' => 'first',
            'condition_percentage' => 90,
        ]);
        $appraisal->setRelation('vehicle', $vehicle);
        $listings = collect(range(1, 8))
            ->map(fn (int $index): array => $this->listing(
                reference: 'listing-'.$index,
                price: 180_000_000 + ($index * 2_000_000),
                mileage: 38_000 + ($index * 1_500),
            ))
            ->all();
        $listings[] = $listings[0];
        $listings[] = $this->listing(
            reference: 'outlier',
            price: 900_000_000,
            mileage: 40_000,
        );

        $result = app(AppraisalValuationEngine::class)->estimate($appraisal, $listings);

        self::assertSame(AppraisalMarketEstimateStatus::Ready, $result['status']);
        self::assertSame(AppraisalConfidence::Medium, $result['confidence']);
        self::assertSame(8, $result['comparable_count']);
        self::assertLessThan($result['market_mid'], $result['trade_in_high']);
        self::assertLessThan($result['market_low'], $result['trade_in_low']);
        self::assertSame(1, collect($result['comparables'])->where('is_duplicate', true)->count());
        self::assertSame(1, collect($result['comparables'])->where('is_outlier', true)->count());
        self::assertSame('weighted_comparable_median_v1', $result['calculation']['algorithm']);
    }

    public function test_it_applies_the_ten_percent_market_correction_to_petrol_units(): void
    {
        $appraisal = $this->appraisal(fuelType: 'gasoline');

        $result = app(AppraisalValuationEngine::class)->estimate(
            $appraisal,
            $this->comparableListings(fuelType: 'gasoline'),
        );

        $correction = collect($result['adjustments'])
            ->firstWhere('code', 'market_correction');
        self::assertNotNull($correction);
        self::assertSame(10.0, $correction['percentage']);
    }

    public function test_it_applies_the_twenty_percent_market_correction_to_diesel_units(): void
    {
        $appraisal = $this->appraisal(fuelType: 'diesel');

        $result = app(AppraisalValuationEngine::class)->estimate(
            $appraisal,
            $this->comparableListings(fuelType: 'diesel'),
        );

        $correction = collect($result['adjustments'])
            ->firstWhere('code', 'market_correction');
        self::assertNotNull($correction);
        self::assertSame(20.0, $correction['percentage']);
    }

    public function test_diesel_trade_in_lands_below_the_petrol_offer_for_identical_listings(): void
    {
        $engine = app(AppraisalValuationEngine::class);

        $petrol = $engine->estimate(
            $this->appraisal(fuelType: 'gasoline'),
            $this->comparableListings(fuelType: 'gasoline'),
        );
        $diesel = $engine->estimate(
            $this->appraisal(fuelType: 'diesel'),
            $this->comparableListings(fuelType: 'diesel'),
        );

        self::assertSame($petrol['market_mid'], $diesel['market_mid']);
        self::assertLessThan($petrol['trade_in_high'], $diesel['trade_in_high']);
        self::assertLessThan($petrol['trade_in_low'], $diesel['trade_in_low']);
    }

    public function test_it_caps_the_total_deduction_so_stacked_penalties_stay_reasonable(): void
    {
        $appraisal = $this->appraisal(fuelType: 'diesel');
        $appraisal->flood_history = 'yes';
        $appraisal->major_accident_history = 'yes';
        $appraisal->service_history = 'none';
        $appraisal->ownership = 'more';
        $appraisal->tax_status = 'overdue';

        $result = app(AppraisalValuationEngine::class)->estimate(
            $appraisal,
            $this->comparableListings(fuelType: 'diesel'),
        );

        $cap = (float) config('appraisal.market_data.maximum_total_deduction_percent');
        $stacked = collect($result['adjustments'])->sum(
            fn (array $adjustment): float => (float) $adjustment['percentage'],
        );
        self::assertGreaterThan($cap, $stacked);
        self::assertGreaterThanOrEqual(
            (int) round($result['market_low'] * (1 - ($cap / 100))) - 500_000,
            $result['trade_in_low'],
        );
    }

    private function appraisal(string $fuelType): Appraisal
    {
        $vehicle = new Vehicle([
            'make' => 'Toyota',
            'model' => 'Innova',
            'variant' => '2.4 G',
            'year' => 2022,
            'transmission' => 'automatic',
            'fuel_type' => $fuelType,
            'mileage' => 42_500,
            'city' => 'Surabaya',
        ]);
        $appraisal = new Appraisal([
            'tax_status' => 'active',
            'flood_history' => 'no',
            'major_accident_history' => 'no',
            'service_history' => 'complete',
            'ownership' => 'first',
            'condition_percentage' => 90,
        ]);
        $appraisal->setRelation('vehicle', $vehicle);

        return $appraisal;
    }

    /** @return list<array<string, mixed>> */
    private function comparableListings(string $fuelType): array
    {
        return collect(range(1, 8))
            ->map(fn (int $index): array => [
                ...$this->listing(
                    reference: 'correction-'.$index,
                    price: 300_000_000 + ($index * 2_000_000),
                    mileage: 38_000 + ($index * 1_500),
                ),
                'model' => 'Innova',
                'variant' => 'Toyota Innova 2.4 G AT 2022',
                'fuel_type' => $fuelType,
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    private function listing(string $reference, int $price, int $mileage): array
    {
        return [
            'market_data_source_id' => 1,
            'source_code' => 'olx_approved_html',
            'external_reference_hash' => hash('sha256', $reference),
            'make' => 'Toyota',
            'model' => 'Avanza',
            'variant' => 'Toyota Avanza 1.5 G AT 2022',
            'year' => 2022,
            'transmission' => 'automatic',
            'fuel_type' => 'gasoline',
            'mileage' => $mileage,
            'listing_price' => $price,
            'city' => 'Surabaya',
            'observed_at' => Carbon::now()->subDay(),
            'metadata' => ['parser' => 'test'],
        ];
    }
}
