<?php

namespace App\Models;

use App\Support\Enums\ToyotaServiceBookingStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $service_booking_id
 * @property ToyotaServiceBookingStatus $status
 * @property string $event
 * @property string $title
 * @property string|null $description
 * @property string|null $reason_code
 * @property bool $user_visible
 * @property int|null $changed_by
 * @property string $actor_type
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 */
class ToyotaServiceBookingStatusHistory extends Model
{
    use HasUuids;

    protected $fillable = [
        'status',
        'event',
        'title',
        'description',
        'reason_code',
        'user_visible',
        'changed_by',
        'actor_type',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => ToyotaServiceBookingStatus::class,
            'user_visible' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<ToyotaServiceBooking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(ToyotaServiceBooking::class, 'service_booking_id');
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
