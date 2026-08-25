<?php

namespace App\Services;

use App\Models\Appraisal;
use App\Support\Enums\AppraisalConfidence;
use App\Support\Enums\AppraisalMarketEstimateStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AppraisalValuationEngine
{
    /**
     * @param  list<array<string, mixed>>  $listings
     * @return array{
     *     status: AppraisalMarketEstimateStatus,
     *     market_low: int|null,
     *     market_mid: int|null,
     *     market_high: int|null,
     *     trade_in_low: int|null,
     *     trade_in_high: int|null,
     *     confidence: AppraisalConfidence,
     *     comparable_count: int,
     *     data_as_of: Carbon|null,
     *     adjustments: list<array<string, mixed>>,
     *     calculation: array<string, mixed>,
     *     comparables: list<array<string, mixed>>
     * }
     */
    public function estimate(Appraisal $appraisal, array $listings): array
    {
        $appraisal->loadMissing('vehicle');
        $minimumPrice = (int) config('appraisal.market_data.minimum_price');
        $maximumPrice = (int) config('appraisal.market_data.maximum_price');
        $maximumAgeDays = (int) config('appraisal.market_data.maximum_age_days');
        $minimumSimilarity = (float) config('appraisal.market_data.minimum_similarity');
        $minimumComparables = (int) config('appraisal.market_data.minimum_comparables');
        $seen = [];
        $comparables = [];

        foreach ($listings as $listing) {
            $deduplicationHash = $this->deduplicationHash($listing);
            $isDuplicate = isset($seen[$deduplicationHash]);
            $seen[$deduplicationHash] = true;
            $observedAt = $listing['observed_at'] instanceof Carbon
                ? $listing['observed_at']
                : Carbon::parse((string) $listing['observed_at']);
            $similarity = $this->similarity($appraisal, $listing, $observedAt);
            $price = (int) ($listing['listing_price'] ?? 0);
            $exclusionReason = match (true) {
                $price < $minimumPrice || $price > $maximumPrice => 'invalid_price',
                $observedAt->lt(now()->subDays($maximumAgeDays)) => 'stale_listing',
                abs((int) $listing['year'] - $appraisal->vehicle->year) > 2 => 'year_outside_window',
                $similarity < $minimumSimilarity => 'low_similarity',
                $isDuplicate => 'duplicate',
                default => null,
            };

            $comparables[] = [
                ...$listing,
                'deduplication_hash' => $deduplicationHash,
                'observed_at' => $observedAt,
                'similarity_score' => round($similarity, 4),
                'weight' => round(max(0.01, $similarity), 4),
                'is_duplicate' => $isDuplicate,
                'is_outlier' => false,
                'exclusion_reason' => $exclusionReason,
            ];
        }

        $eligibleIndexes = collect($comparables)
            ->keys()
            ->filter(fn (int $index): bool => $comparables[$index]['exclusion_reason'] === null)
            ->values()
            ->all();
        $outlierIndexes = $this->outlierIndexes($comparables, $eligibleIndexes);
        foreach ($outlierIndexes as $index) {
            $comparables[$index]['is_outlier'] = true;
            $comparables[$index]['exclusion_reason'] = 'price_outlier';
        }

        $valid = collect($comparables)
            ->filter(fn (array $item): bool => $item['exclusion_reason'] === null)
            ->values()
            ->all();
        $count = count($valid);
        $prices = array_map(
            fn (array $item): array => [
                'price' => (int) $item['listing_price'],
                'weight' => (float) $item['weight'],
            ],
            $valid,
        );
        $marketLow = $this->weightedPercentile($prices, 0.25);
        $marketMid = $this->weightedPercentile($prices, 0.50);
        $marketHigh = $this->weightedPercentile($prices, 0.75);
        $dispersion = $marketMid !== null && $marketMid > 0
            ? (($marketHigh ?? $marketMid) - ($marketLow ?? $marketMid)) / $marketMid
            : 1.0;
        $confidence = $this->confidence($count, $dispersion);
        $adjustments = $this->adjustments($appraisal);
        $deduction = $this->deduction($adjustments);

        if ($marketLow !== null && $marketMid !== null && $marketHigh !== null) {
            $marketLow = $this->roundPrice($marketLow);
            $marketMid = $this->roundPrice($marketMid);
            $marketHigh = $this->roundPrice($marketHigh);
            $tradeInLow = $this->roundPrice((int) round($marketLow * (1 - ($deduction / 100))));
            $tradeInHigh = $this->roundPrice((int) round(
                $marketMid * (1 - (max(3.0, $deduction - 1.5) / 100)),
            ));
            $tradeInHigh = min($tradeInHigh, $marketMid);
            $tradeInLow = min($tradeInLow, $tradeInHigh);
        } else {
            $tradeInLow = null;
            $tradeInHigh = null;
        }

        return [
            'status' => $count >= $minimumComparables
                ? AppraisalMarketEstimateStatus::Ready
                : AppraisalMarketEstimateStatus::Insufficient,
            'market_low' => $marketLow,
            'market_mid' => $marketMid,
            'market_high' => $marketHigh,
            'trade_in_low' => $tradeInLow,
            'trade_in_high' => $tradeInHigh,
            'confidence' => $confidence,
            'comparable_count' => $count,
            'data_as_of' => $valid === []
                ? null
                : collect($valid)->max('observed_at'),
            'adjustments' => $adjustments,
            'calculation' => [
                'algorithm' => 'weighted_comparable_median_v1',
                'fetched_count' => count($listings),
                'eligible_before_outlier_count' => count($eligibleIndexes),
                'valid_count' => $count,
                'dispersion' => round($dispersion, 4),
                'minimum_similarity' => $minimumSimilarity,
                'minimum_comparables' => $minimumComparables,
                'maximum_age_days' => $maximumAgeDays,
                'percentiles' => [25, 50, 75],
                'weights' => [
                    'model_variant' => 0.30,
                    'year' => 0.20,
                    'transmission' => 0.075,
                    'fuel_type' => 0.075,
                    'mileage' => 0.15,
                    'location' => 0.10,
                    'recency' => 0.10,
                ],
            ],
            'comparables' => $comparables,
        ];
    }

    /**
     * Converts a validated OpenAI market-price decision into the same immutable
     * valuation contract used by the comparable engine. Trade-in deductions
     * remain deterministic and are never delegated to the model.
     *
     * @param  array{
     *     market_low: int,
     *     market_mid: int,
     *     market_high: int,
     *     confidence: string,
     *     rationale: string,
     *     assumptions: list<string>,
     *     model: string,
     *     response_id: string|null,
     *     decided_at: Carbon
     * }  $decision
     * @param  array{
     *     status: AppraisalMarketEstimateStatus,
     *     market_low: int|null,
     *     market_mid: int|null,
     *     market_high: int|null,
     *     trade_in_low: int|null,
     *     trade_in_high: int|null,
     *     confidence: AppraisalConfidence,
     *     comparable_count: int,
     *     data_as_of: Carbon|null,
     *     adjustments: list<array<string, mixed>>,
     *     calculation: array<string, mixed>,
     *     comparables: list<array<string, mixed>>
     * }  $evidence
     * @return array{
     *     status: AppraisalMarketEstimateStatus,
     *     market_low: int,
     *     market_mid: int,
     *     market_high: int,
     *     trade_in_low: int,
     *     trade_in_high: int,
     *     confidence: AppraisalConfidence,
     *     comparable_count: int,
     *     data_as_of: Carbon,
     *     adjustments: list<array<string, mixed>>,
     *     calculation: array<string, mixed>,
     *     comparables: list<array<string, mixed>>
     * }
     */
    public function estimateFromPriceDecision(
        Appraisal $appraisal,
        array $decision,
        array $evidence,
    ): array {
        $appraisal->loadMissing('vehicle');
        $marketLow = $this->roundPrice($decision['market_low']);
        $marketMid = $this->roundPrice($decision['market_mid']);
        $marketHigh = $this->roundPrice($decision['market_high']);
        $marketMid = max($marketLow, min($marketMid, $marketHigh));
        $adjustments = $this->adjustments($appraisal);
        $deduction = $this->deduction($adjustments);
        $tradeInLow = $this->roundPrice((int) round(
            $marketLow * (1 - ($deduction / 100)),
        ));
        $tradeInHigh = $this->roundPrice((int) round(
            $marketMid * (1 - (max(3.0, $deduction - 1.5) / 100)),
        ));
        $tradeInHigh = min($tradeInHigh, $marketMid);
        $tradeInLow = min($tradeInLow, $tradeInHigh);
        $confidence = AppraisalConfidence::from($decision['confidence']);
        if ($evidence['comparable_count'] === 0) {
            $confidence = AppraisalConfidence::Low;
        }

        return [
            'status' => AppraisalMarketEstimateStatus::Ready,
            'market_low' => $marketLow,
            'market_mid' => $marketMid,
            'market_high' => $marketHigh,
            'trade_in_low' => $tradeInLow,
            'trade_in_high' => $tradeInHigh,
            'confidence' => $confidence,
            'comparable_count' => $evidence['comparable_count'],
            'data_as_of' => $decision['decided_at'],
            'adjustments' => $adjustments,
            'calculation' => [
                'algorithm' => 'openai_price_decision_with_deterministic_trade_in_v1',
                'fallback_reason' => 'insufficient_olx_comparables',
                'olx_valid_comparable_count' => $evidence['comparable_count'],
                'minimum_comparables' => (int) config(
                    'appraisal.market_data.minimum_comparables',
                ),
                'ai_price_decision' => [
                    'model' => $decision['model'],
                    'response_id' => $decision['response_id'],
                    'confidence' => $confidence->value,
                    'rationale' => $decision['rationale'],
                    'assumptions' => $decision['assumptions'],
                    'decided_at' => $decision['decided_at']->toIso8601String(),
                ],
            ],
            'comparables' => $evidence['comparables'],
        ];
    }

    /** @param array<string, mixed> $listing */
    private function similarity(
        Appraisal $appraisal,
        array $listing,
        Carbon $observedAt,
    ): float {
        $vehicle = $appraisal->vehicle;
        $modelMatches = $this->normalize((string) ($listing['model'] ?? ''))
            === $this->normalize($vehicle->model);
        $listingVariant = $this->normalize((string) ($listing['variant'] ?? ''));
        $targetVariant = $this->normalize($vehicle->variant);
        $variantMatches = $targetVariant !== ''
            && str_contains($listingVariant, $targetVariant);
        $modelVariant = match (true) {
            ! $modelMatches => 0.0,
            $variantMatches => 1.0,
            default => 0.70,
        };
        $yearDifference = abs((int) ($listing['year'] ?? 0) - $vehicle->year);
        $year = max(0.0, 1 - ($yearDifference / 3));
        $transmission = $this->attributeScore(
            $listing['transmission'] ?? null,
            $vehicle->transmission,
        );
        $fuelType = $this->attributeScore(
            $listing['fuel_type'] ?? null,
            $vehicle->fuel_type,
        );
        $listingMileage = $listing['mileage'] ?? null;
        $mileage = $listingMileage === null
            ? 0.5
            : max(0.0, 1 - (abs((int) $listingMileage - $vehicle->mileage) / 100_000));
        $listingCity = $this->normalize((string) ($listing['city'] ?? ''));
        $vehicleCity = $this->normalize($vehicle->city);
        $location = match (true) {
            $listingCity === '' => 0.5,
            $listingCity === $vehicleCity,
            str_contains($listingCity, $vehicleCity),
            str_contains($vehicleCity, $listingCity) => 1.0,
            default => 0.4,
        };
        $ageDays = max(0, (int) floor($observedAt->diffInDays(now())));
        $recency = max(0.0, 1 - ($ageDays / max(
            1,
            (int) config('appraisal.market_data.maximum_age_days'),
        )));

        return ($modelVariant * 0.30)
            + ($year * 0.20)
            + ($transmission * 0.075)
            + ($fuelType * 0.075)
            + ($mileage * 0.15)
            + ($location * 0.10)
            + ($recency * 0.10);
    }

    private function attributeScore(mixed $actual, string $expected): float
    {
        if (! is_string($actual) || blank($actual)) {
            return 0.5;
        }

        return $this->normalize($actual) === $this->normalize($expected) ? 1.0 : 0.0;
    }

    private function normalize(string $value): string
    {
        return (string) Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '');
    }

    /** @param array<string, mixed> $listing */
    private function deduplicationHash(array $listing): string
    {
        if (filled($listing['external_reference_hash'] ?? null)) {
            return (string) $listing['external_reference_hash'];
        }

        return hash('sha256', implode('|', [
            $this->normalize((string) ($listing['make'] ?? '')),
            $this->normalize((string) ($listing['model'] ?? '')),
            $this->normalize((string) ($listing['variant'] ?? '')),
            (string) ($listing['year'] ?? ''),
            (string) ($listing['mileage'] ?? ''),
            (string) ($listing['listing_price'] ?? ''),
            $this->normalize((string) ($listing['city'] ?? '')),
        ]));
    }

    /**
     * @param  list<array<string, mixed>>  $comparables
     * @param  list<int>  $eligibleIndexes
     * @return list<int>
     */
    private function outlierIndexes(array $comparables, array $eligibleIndexes): array
    {
        if (count($eligibleIndexes) < 4) {
            return [];
        }

        $prices = collect($eligibleIndexes)
            ->map(fn (int $index): int => (int) $comparables[$index]['listing_price'])
            ->sort()
            ->values()
            ->all();
        $q1 = $this->plainPercentile($prices, 0.25);
        $q3 = $this->plainPercentile($prices, 0.75);
        $iqr = $q3 - $q1;
        $lower = max(0, $q1 - (1.5 * $iqr));
        $upper = $q3 + (1.5 * $iqr);

        return collect($eligibleIndexes)
            ->filter(function (int $index) use ($comparables, $lower, $upper): bool {
                $price = (int) $comparables[$index]['listing_price'];

                return $price < $lower || $price > $upper;
            })
            ->values()
            ->all();
    }

    /** @param list<int> $sorted */
    private function plainPercentile(array $sorted, float $percentile): float
    {
        $position = (count($sorted) - 1) * $percentile;
        $lower = (int) floor($position);
        $upper = (int) ceil($position);
        if ($lower === $upper) {
            return (float) $sorted[$lower];
        }

        return $sorted[$lower] + (($sorted[$upper] - $sorted[$lower]) * ($position - $lower));
    }

    /**
     * @param  list<array{price: int, weight: float}>  $prices
     */
    private function weightedPercentile(array $prices, float $percentile): ?int
    {
        if ($prices === []) {
            return null;
        }

        usort($prices, fn (array $left, array $right): int => $left['price'] <=> $right['price']);
        $totalWeight = array_sum(array_column($prices, 'weight'));
        $threshold = $totalWeight * $percentile;
        $cumulative = 0.0;
        foreach ($prices as $item) {
            $cumulative += $item['weight'];
            if ($cumulative >= $threshold) {
                return $item['price'];
            }
        }

        return $prices[array_key_last($prices)]['price'];
    }

    private function confidence(int $count, float $dispersion): AppraisalConfidence
    {
        if (
            $dispersion > (float) config('appraisal.market_data.maximum_stable_dispersion')
            || $count < (int) config('appraisal.market_data.minimum_comparables')
        ) {
            return AppraisalConfidence::Low;
        }

        return $count >= (int) config('appraisal.market_data.high_confidence_comparables')
            ? AppraisalConfidence::High
            : AppraisalConfidence::Medium;
    }

    /** @return list<array<string, mixed>> */
    private function adjustments(Appraisal $appraisal): array
    {
        $adjustments = [[
            'code' => 'dealer_margin',
            'label' => 'Margin dan biaya proses trade-in',
            'percentage' => (float) config('appraisal.market_data.dealer_margin_percent'),
        ]];
        $marketCorrection = $this->marketCorrectionPercent($appraisal);
        if ($marketCorrection > 0.0) {
            $adjustments[] = [
                'code' => 'market_correction',
                'label' => 'Penyesuaian harga pasar Auto2000',
                'percentage' => $marketCorrection,
            ];
        }
        $conditionAdjustment = round((90 - $appraisal->condition_percentage) * 0.15, 2);
        if ($conditionAdjustment !== 0.0) {
            $adjustments[] = [
                'code' => 'vehicle_condition',
                'label' => 'Kondisi kendaraan',
                'percentage' => max(-2.0, min(8.0, $conditionAdjustment)),
            ];
        }

        $penalties = [
            ['field' => 'tax_status', 'value' => 'overdue', 'code' => 'tax', 'label' => 'Status pajak', 'percentage' => 2.0],
            ['field' => 'flood_history', 'value' => 'yes', 'code' => 'flood', 'label' => 'Riwayat banjir', 'percentage' => 15.0],
            ['field' => 'major_accident_history', 'value' => 'yes', 'code' => 'major_accident', 'label' => 'Riwayat tabrakan berat', 'percentage' => 12.0],
            ['field' => 'service_history', 'value' => 'partial', 'code' => 'service_partial', 'label' => 'Riwayat servis sebagian', 'percentage' => 1.0],
            ['field' => 'service_history', 'value' => 'none', 'code' => 'service_none', 'label' => 'Riwayat servis tidak tersedia', 'percentage' => 2.5],
            ['field' => 'ownership', 'value' => 'second', 'code' => 'ownership_second', 'label' => 'Kepemilikan kedua', 'percentage' => 0.5],
            ['field' => 'ownership', 'value' => 'more', 'code' => 'ownership_more', 'label' => 'Kepemilikan lebih dari dua', 'percentage' => 1.0],
            ['field' => 'engine_condition', 'value' => 'wet', 'code' => 'engine_wet', 'label' => 'Mesin basah atau rembes', 'percentage' => 4.0],
            ['field' => 'tyre_condition', 'value' => 'damaged', 'code' => 'tyre_damaged', 'label' => 'Ban perlu diganti', 'percentage' => 2.0],
        ];
        foreach ($penalties as $penalty) {
            if ($appraisal->{$penalty['field']} === $penalty['value']) {
                unset($penalty['field'], $penalty['value']);
                $adjustments[] = $penalty;
            }
        }

        return $adjustments;
    }

    /**
     * Menjumlahkan seluruh potongan lalu menahannya pada batas atas konfigurasi
     * supaya tumpukan penalti tidak menghasilkan penawaran yang tidak wajar.
     *
     * @param  list<array<string, mixed>>  $adjustments
     */
    private function deduction(array $adjustments): float
    {
        $total = collect($adjustments)->sum(
            fn (array $adjustment): float => (float) $adjustment['percentage'],
        );

        return min(
            (float) $total,
            (float) config('appraisal.market_data.maximum_total_deduction_percent'),
        );
    }

    /**
     * Koreksi harga pasar yang diminta cabang: 10% untuk seluruh unit dan 20%
     * untuk unit diesel, karena harga diesel paling jauh selisihnya dari
     * penawaran nyata OLX.
     */
    private function marketCorrectionPercent(Appraisal $appraisal): float
    {
        $isDiesel = $this->normalize((string) ($appraisal->vehicle?->fuel_type ?? '')) === 'diesel';

        return (float) config(
            $isDiesel
                ? 'appraisal.market_data.diesel_market_correction_percent'
                : 'appraisal.market_data.market_correction_percent',
        );
    }

    private function roundPrice(int $price): int
    {
        $rounding = max(1, (int) config('appraisal.market_data.rounding'));

        return (int) (round($price / $rounding) * $rounding);
    }
}
