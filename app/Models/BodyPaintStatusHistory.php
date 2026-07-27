<?php

namespace App\Models;

use App\Support\Enums\BodyPaintEstimateStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $estimate_id
 * @property BodyPaintEstimateStatus $status
 * @property string $event
 * @property string $title
 * @property string|null $description
 * @property string|null $reason_code
 * @property bool $user_visible
 * @property int|null $changed_by_user_id
 * @property string $actor_type
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BodyPaintStatusHistory extends Model
{
    protected $table = 'body_paint_status_histories';

    protected $fillable = [
        'status',
        'event',
        'title',
        'description',
        'reason_code',
        'user_visible',
        'actor_type',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => BodyPaintEstimateStatus::class,
            'user_visible' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<BodyPaintEstimate, $this> */
    public function estimate(): BelongsTo
    {
        return $this->belongsTo(BodyPaintEstimate::class, 'estimate_id');
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
