<?php

namespace App\Models;

use App\Support\Enums\BodyPaintSeverity;
use App\Support\Enums\BodyPaintWorkType;
use Database\Factories\BodyPaintPriceItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $matrix_code
 * @property string $item_code
 * @property int $version
 * @property string|null $service_location_id
 * @property int|null $vehicle_make_id
 * @property int|null $vehicle_model_id
 * @property string|null $vehicle_class
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
 * @property bool $is_high_risk
 * @property bool $is_active
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string $source_reference
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 */
class BodyPaintPriceItem extends Model
{
    /** @use HasFactory<BodyPaintPriceItemFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'matrix_code',
        'item_code',
        'version',
        'service_location_id',
        'vehicle_make_id',
        'vehicle_model_id',
        'vehicle_class',
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
        'is_high_risk',
        'is_active',
        'effective_from',
        'effective_to',
        'source_reference',
        'approved_by',
        'approved_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('body_paint_price_matrix')
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'severity' => BodyPaintSeverity::class,
            'work_type' => BodyPaintWorkType::class,
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
            'is_high_risk' => 'boolean',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    /** @param Builder<BodyPaintPriceItem> $query */
    public function scopeEffective(Builder $query, ?Carbon $at = null): void
    {
        $date = ($at ?? now())->toDateString();
        $query->where('is_active', true)
            ->whereNotNull('approved_at')
            ->whereDate('effective_from', '<=', $date)
            ->where(function (Builder $builder) use ($date): void {
                $builder->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            });
    }

    /** @return BelongsTo<ToyotaServiceLocation, $this> */
    public function serviceLocation(): BelongsTo
    {
        return $this->belongsTo(ToyotaServiceLocation::class);
    }

    /** @return BelongsTo<VehicleMake, $this> */
    public function vehicleMake(): BelongsTo
    {
        return $this->belongsTo(VehicleMake::class);
    }

    /** @return BelongsTo<VehicleModel, $this> */
    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
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
}
