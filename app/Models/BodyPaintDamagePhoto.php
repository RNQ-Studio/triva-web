<?php

namespace App\Models;

use App\Support\Enums\BodyPaintPhotoReviewStatus;
use App\Support\Enums\BodyPaintPhotoType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $estimate_id
 * @property string|null $damage_id
 * @property string $asset_id
 * @property BodyPaintPhotoType $photo_type
 * @property BodyPaintPhotoReviewStatus $review_status
 * @property string|null $rejection_reason_code
 * @property string|null $rejection_reason
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Asset $asset
 */
class BodyPaintDamagePhoto extends Model
{
    protected $table = 'body_paint_damage_photos';

    protected $fillable = [
        'asset_id',
        'photo_type',
        'review_status',
        'rejection_reason_code',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'photo_type' => BodyPaintPhotoType::class,
            'review_status' => BodyPaintPhotoReviewStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<BodyPaintEstimate, $this> */
    public function estimate(): BelongsTo
    {
        return $this->belongsTo(BodyPaintEstimate::class, 'estimate_id');
    }

    /** @return BelongsTo<BodyPaintEstimateDamage, $this> */
    public function damage(): BelongsTo
    {
        return $this->belongsTo(BodyPaintEstimateDamage::class, 'damage_id');
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
