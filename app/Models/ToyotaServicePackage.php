<?php

namespace App\Models;

use Database\Factories\ToyotaServicePackageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string|null $vehicle_model
 * @property int $km_interval
 * @property int $parts_cost
 * @property int $labor_cost
 * @property list<string>|null $includes
 * @property int $duration_min_minutes
 * @property int $duration_max_minutes
 * @property bool $is_active
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string $source_reference
 */
class ToyotaServicePackage extends Model
{
    /** @use HasFactory<ToyotaServicePackageFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'code',
        'name',
        'description',
        'vehicle_model',
        'km_interval',
        'parts_cost',
        'labor_cost',
        'includes',
        'duration_min_minutes',
        'duration_max_minutes',
        'is_active',
        'effective_from',
        'effective_to',
        'source_reference',
    ];

    protected function casts(): array
    {
        return [
            'km_interval' => 'integer',
            'parts_cost' => 'integer',
            'labor_cost' => 'integer',
            'includes' => 'array',
            'duration_min_minutes' => 'integer',
            'duration_max_minutes' => 'integer',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('toyota_service_package')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** @param Builder<ToyotaServicePackage> $query */
    public function scopeEffective(Builder $query, ?Carbon $onDate = null): void
    {
        $date = ($onDate ?? now('Asia/Jakarta'))->toDateString();

        $query->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(function (Builder $builder) use ($date): void {
                $builder->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            });
    }

    public function totalCost(): int
    {
        return $this->parts_cost + $this->labor_cost;
    }
}
