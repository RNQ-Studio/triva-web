<?php

namespace App\Models;

use App\Support\Enums\AppraisalConfidence;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $appraisal_id
 * @property string|null $market_estimate_id
 * @property int $version
 * @property int $market_low
 * @property int $market_mid
 * @property int $market_high
 * @property int $trade_in_low
 * @property int $trade_in_high
 * @property AppraisalConfidence $confidence
 * @property int $comparable_count
 * @property Carbon $data_as_of
 * @property Carbon $valid_until
 * @property bool $requires_physical_inspection
 * @property string $disclaimer
 * @property array<string, mixed>|null $adjustments
 * @property string $publication_type
 * @property string|null $override_reason_code
 * @property string|null $override_notes
 * @property int|null $published_by
 * @property Carbon $published_at
 */
class AppraisalResult extends Model
{
    use HasUuids;

    protected $fillable = [
        'market_estimate_id',
        'version',
        'market_low',
        'market_mid',
        'market_high',
        'trade_in_low',
        'trade_in_high',
        'confidence',
        'comparable_count',
        'data_as_of',
        'valid_until',
        'requires_physical_inspection',
        'disclaimer',
        'adjustments',
        'publication_type',
        'override_reason_code',
        'override_notes',
        'published_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'market_low' => 'integer',
            'market_mid' => 'integer',
            'market_high' => 'integer',
            'trade_in_low' => 'integer',
            'trade_in_high' => 'integer',
            'confidence' => AppraisalConfidence::class,
            'comparable_count' => 'integer',
            'data_as_of' => 'datetime',
            'valid_until' => 'datetime',
            'requires_physical_inspection' => 'boolean',
            'adjustments' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Appraisal, $this> */
    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(Appraisal::class);
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** @return BelongsTo<AppraisalMarketEstimate, $this> */
    public function marketEstimate(): BelongsTo
    {
        return $this->belongsTo(AppraisalMarketEstimate::class, 'market_estimate_id');
    }

    /** @return HasMany<AppraisalComparable, $this> */
    public function comparables(): HasMany
    {
        return $this->hasMany(AppraisalComparable::class);
    }
}
