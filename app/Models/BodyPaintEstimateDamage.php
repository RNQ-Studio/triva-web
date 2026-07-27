<?php

namespace App\Models;

use App\Support\Enums\BodyPaintSeverity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $estimate_id
 * @property string $panel_code
 * @property string $damage_type
 * @property BodyPaintSeverity $customer_severity
 * @property BodyPaintSeverity|null $estimator_severity
 * @property string|null $customer_note
 * @property string|null $estimator_note
 * @property bool $is_high_risk
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, BodyPaintDamagePhoto> $photos
 */
class BodyPaintEstimateDamage extends Model
{
    use HasUuids;

    protected $table = 'body_paint_estimate_damages';

    protected $fillable = [
        'panel_code',
        'damage_type',
        'customer_severity',
        'estimator_severity',
        'customer_note',
        'estimator_note',
        'is_high_risk',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'customer_severity' => BodyPaintSeverity::class,
            'estimator_severity' => BodyPaintSeverity::class,
            'is_high_risk' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<BodyPaintEstimate, $this> */
    public function estimate(): BelongsTo
    {
        return $this->belongsTo(BodyPaintEstimate::class, 'estimate_id');
    }

    /** @return HasMany<BodyPaintDamagePhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(BodyPaintDamagePhoto::class, 'damage_id');
    }

    /** @return HasMany<BodyPaintEstimateItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BodyPaintEstimateItem::class, 'damage_id');
    }
}
