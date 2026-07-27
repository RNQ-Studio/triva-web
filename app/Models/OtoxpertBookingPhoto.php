<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $booking_id
 * @property string $asset_id
 * @property int $sort_order
 */
class OtoxpertBookingPhoto extends Model
{
    protected $fillable = ['asset_id', 'sort_order'];

    /** @return BelongsTo<OtoxpertBooking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(OtoxpertBooking::class, 'booking_id');
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
