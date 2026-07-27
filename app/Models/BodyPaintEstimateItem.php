<?php

namespace App\Models;

use App\Support\Enums\BodyPaintSeverity;
use App\Support\Enums\BodyPaintWorkType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $estimate_id
 * @property string|null $damage_id
 * @property string|null $price_item_id
 * @property int|null $estimate_version
 * @property string|null $matrix_code
 * @property int|null $matrix_version
 * @property string $panel_code
 * @property string $damage_type
 * @property BodyPaintSeverity $severity
 * @property BodyPaintWorkType $work_type
 * @property int $labor_low
 * @property int $labor_high
 * @property int $material_low
 * @property int $material_high
 * @property int $parts_low
 * @property int $parts_high
 * @property int $other_low
 * @property int $other_high
 * @property int $duration_min_hours
 * @property int $duration_max_hours
 * @property string|null $recommendation
 * @property bool $is_engine_item
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BodyPaintEstimateItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'damage_id',
        'price_item_id',
        'estimate_version',
        'matrix_code',
        'matrix_version',
        'panel_code',
        'damage_type',
        'severity',
        'work_type',
        'labor_low',
        'labor_high',
        'material_low',
        'material_high',
        'parts_low',
        'parts_high',
        'other_low',
        'other_high',
        'duration_min_hours',
        'duration_max_hours',
        'recommendation',
        'is_engine_item',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'severity' => BodyPaintSeverity::class,
            'work_type' => BodyPaintWorkType::class,
            'estimate_version' => 'integer',
            'matrix_version' => 'integer',
            'labor_low' => 'integer',
            'labor_high' => 'integer',
            'material_low' => 'integer',
            'material_high' => 'integer',
            'parts_low' => 'integer',
            'parts_high' => 'integer',
            'other_low' => 'integer',
            'other_high' => 'integer',
            'duration_min_hours' => 'integer',
            'duration_max_hours' => 'integer',
            'is_engine_item' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function totalLow(): int
    {
        return $this->labor_low
            + $this->material_low
            + $this->parts_low
            + $this->other_low;
    }

    public function totalHigh(): int
    {
        return $this->labor_high
            + $this->material_high
            + $this->parts_high
            + $this->other_high;
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

    /** @return BelongsTo<BodyPaintPriceItem, $this> */
    public function priceItem(): BelongsTo
    {
        return $this->belongsTo(BodyPaintPriceItem::class);
    }
}
