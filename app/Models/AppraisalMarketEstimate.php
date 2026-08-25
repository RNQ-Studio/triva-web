<?php

namespace App\Models;

use App\Support\Enums\AppraisalConfidence;
use App\Support\Enums\AppraisalMarketEstimateStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $appraisal_id
 * @property int $version
 * @property AppraisalMarketEstimateStatus $status
 * @property int|null $market_low
 * @property int|null $market_mid
 * @property int|null $market_high
 * @property int|null $trade_in_low
 * @property int|null $trade_in_high
 * @property AppraisalConfidence $confidence
 * @property int $comparable_count
 * @property Carbon|null $data_as_of
 * @property list<string>|null $provider_codes
 * @property list<array<string, mixed>>|null $adjustments
 * @property array<string, mixed>|null $calculation
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property string|null $valuation_fingerprint
 * @property Carbon $calculated_at
 */
class AppraisalMarketEstimate extends Model
{
    use HasUuids;

    protected $fillable = [
        'version',
        'status',
        'market_low',
        'market_mid',
        'market_high',
        'trade_in_low',
        'trade_in_high',
        'confidence',
        'comparable_count',
        'data_as_of',
        'provider_codes',
        'adjustments',
        'calculation',
        'failure_code',
        'failure_message',
        'calculated_at',
        'valuation_fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => AppraisalMarketEstimateStatus::class,
            'market_low' => 'integer',
            'market_mid' => 'integer',
            'market_high' => 'integer',
            'trade_in_low' => 'integer',
            'trade_in_high' => 'integer',
            'confidence' => AppraisalConfidence::class,
            'comparable_count' => 'integer',
            'data_as_of' => 'datetime',
            'provider_codes' => 'array',
            'adjustments' => 'array',
            'calculation' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Appraisal, $this> */
    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(Appraisal::class);
    }

    /** @return HasMany<AppraisalMarketComparable, $this> */
    public function comparables(): HasMany
    {
        return $this->hasMany(AppraisalMarketComparable::class);
    }
}
