<?php

namespace App\Models;

use App\Support\Enums\AppraisalPhotoAngle;
use App\Support\Enums\AppraisalPhotoReviewStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $appraisal_id
 * @property string $asset_id
 * @property AppraisalPhotoAngle $angle
 * @property int $version
 * @property bool $is_current
 * @property AppraisalPhotoReviewStatus $review_status
 * @property string|null $rejection_note
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property-read Asset|null $asset
 */
class AppraisalPhoto extends Model
{
    use HasUuids;

    protected $fillable = [
        'asset_id',
        'angle',
        'version',
        'is_current',
        'review_status',
        'rejection_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'angle' => AppraisalPhotoAngle::class,
            'review_status' => AppraisalPhotoReviewStatus::class,
            'version' => 'integer',
            'is_current' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Appraisal, $this> */
    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(Appraisal::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
