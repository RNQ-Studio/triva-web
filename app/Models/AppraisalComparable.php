<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppraisalComparable extends Model
{
    use HasUuids;

    protected $fillable = [
        'source_code',
        'external_reference_hash',
        'make',
        'model',
        'variant',
        'year',
        'mileage',
        'listing_price',
        'city',
        'observed_at',
        'similarity_score',
        'is_outlier',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'mileage' => 'integer',
            'listing_price' => 'integer',
            'similarity_score' => 'decimal:4',
            'is_outlier' => 'boolean',
            'metadata' => 'array',
            'observed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AppraisalResult, $this> */
    public function result(): BelongsTo
    {
        return $this->belongsTo(AppraisalResult::class, 'appraisal_result_id');
    }
}
