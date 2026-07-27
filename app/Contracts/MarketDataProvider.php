<?php

namespace App\Contracts;

use App\Models\Appraisal;
use App\Models\MarketDataSource;
use Illuminate\Support\Carbon;

interface MarketDataProvider
{
    public function code(): string;

    /**
     * @return list<array{
     *     market_data_source_id: int,
     *     source_code: string,
     *     external_reference_hash: string|null,
     *     make: string,
     *     model: string,
     *     variant: string|null,
     *     year: int,
     *     transmission: string|null,
     *     fuel_type: string|null,
     *     mileage: int|null,
     *     listing_price: int,
     *     city: string|null,
     *     observed_at: Carbon,
     *     metadata: array<string, mixed>
     * }>
     */
    public function fetch(Appraisal $appraisal, MarketDataSource $source): array;
}
