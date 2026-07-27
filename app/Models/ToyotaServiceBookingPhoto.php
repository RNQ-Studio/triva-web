<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $service_booking_id
 * @property string $asset_id
 * @property-read Asset $asset
 */
class ToyotaServiceBookingPhoto extends Model
{
    use HasUuids;

    protected $fillable = ['asset_id'];

    /** @return BelongsTo<ToyotaServiceBooking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(ToyotaServiceBooking::class, 'service_booking_id');
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
