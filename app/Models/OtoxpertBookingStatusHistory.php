<?php

namespace App\Models;

use App\Support\Enums\OtoxpertBookingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $booking_id
 * @property OtoxpertBookingStatus $status
 * @property string $event
 * @property string $title
 * @property string|null $description
 * @property string|null $reason_code
 * @property bool $user_visible
 * @property int|null $changed_by_user_id
 * @property string $actor_type
 * @property array<string, mixed>|null $metadata
 */
class OtoxpertBookingStatusHistory extends Model
{
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
            'status' => OtoxpertBookingStatus::class,
            'user_visible' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<OtoxpertBooking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(OtoxpertBooking::class, 'booking_id');
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
