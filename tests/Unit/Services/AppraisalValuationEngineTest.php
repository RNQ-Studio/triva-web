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
