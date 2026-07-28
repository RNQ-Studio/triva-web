<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $vehicle_model_id
 * @property string $slug
 * @property string $name
 * @property int $year_from
 * @property int|null $year_to
 * @property string|null $transmission
 * @property string|null $fuel_type
 * @property array<int, string>|null $aliases
 * @property int $sort_order
 * @property bool $is_active
 * @property string|null $source_url
 * @property Carbon|null $source_checked_at
 * @property-read VehicleModel $vehicleModel
 */
class VehicleVariant extends Model
{
    protected $fillable = [
        'vehicle_model_id',
        'slug',
        'name',
        'year_from',
        'year_to',
        'transmission',
        'fuel_type',
        'aliases',
        'sort_order',
        'is_active',
        'source_url',
        'source_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'year_from' => 'integer',
            'year_to' => 'integer',
            'aliases' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'source_checked_at' => 'date',
        ];
    }

    /** @param Builder<VehicleVariant> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<VehicleVariant> $query */
    public function scopeAvailableInYear(Builder $query, int $year): void
    {
        $query
            ->where('year_from', '<=', $year)
            ->where(function (Builder $query) use ($year): void {
                $query
                    ->whereNull('year_to')
                    ->orWhere('year_to', '>=', $year);
            });
    }

    /** @return BelongsTo<VehicleModel, $this> */
    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class);
    }

    /** @return HasMany<Vehicle, $this> */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }
}
