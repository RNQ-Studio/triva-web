<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $estimate_id
 * @property int $version
 * @property int $total_low
 * @property int $total_high
 * @property int $duration_min_days
 * @property int $duration_max_days
 * @property list<string> $assumptions
 * @property string $disclaimer
 * @property string|null $override_reason_code
 * @property string|null $override_reason
 * @property int $published_by
 * @property Carbon $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $publisher
 */
class BodyPaintEstimateVersion extends Model
{
    use HasUuids;

    protected $fillable = [
        'version',
        'total_low',
        'total_high',
        'duration_min_days',
        'duration_max_days',
        'assumptions',
        'disclaimer',
        'override_reason_code',
        'override_reason',
        'published_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'total_low' => 'integer',
            'total_high' => 'integer',
            'duration_min_days' => 'integer',
            'duration_max_days' => 'integer',
            'assumptions' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<BodyPaintEstimate, $this> */
    public function estimate(): BelongsTo
    {
        return $this->belongsTo(BodyPaintEstimate::class, 'estimate_id');
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
