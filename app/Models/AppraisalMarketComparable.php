<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppraisalMarketComparable extends Model
{
    use HasUuids;

    protected $fillable = [
        'market_data_source_id',
        'source_code',
        'external_reference_hash',
        'deduplication_hash',
        'make',
        'model',
        'variant',
        'year',
        'transmission',
        'fuel_type',
        'mileage',
        'listing_price',
        'city',
        'observed_at',
        'similarity_score',
        'weight',
        'is_duplicate',
        'is_outlier',
        'exclusion_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'mileage' => 'integer',
            'listing_price' => 'integer',
            'observed_at' => 'datetime',
            'similarity_score' => 'decimal:4',
            'weight' => 'decimal:4',
            'is_duplicate' => 'boolean',
            'is_outlier' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<AppraisalMarketEstimate, $this> */
    public function estimate(): BelongsTo
    {
        return $this->belongsTo(
            AppraisalMarketEstimate::class,
            'appraisal_market_estimate_id',
        );
    }

    /** @return BelongsTo<MarketDataSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(MarketDataSource::class, 'market_data_source_id');
    }
}
