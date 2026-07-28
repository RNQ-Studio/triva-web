<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $vehicle_make_id
 * @property string $slug
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 * @property string|null $source_url
 * @property Carbon|null $source_checked_at
 * @property-read VehicleMake $vehicleMake
 * @property-read Collection<int, VehicleVariant> $vehicleVariants
 */
class VehicleModel extends Model
{
    protected $fillable = [
        'vehicle_make_id',
        'slug',
        'name',
        'sort_order',
        'is_active',
        'source_url',
        'source_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'source_checked_at' => 'date',
        ];
    }

    /** @param Builder<VehicleModel> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @return BelongsTo<VehicleMake, $this> */
    public function vehicleMake(): BelongsTo
    {
        return $this->belongsTo(VehicleMake::class);
    }

    /** @return HasMany<Vehicle, $this> */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /** @return HasMany<VehicleVariant, $this> */
    public function vehicleVariants(): HasMany
    {
        return $this->hasMany(VehicleVariant::class);
    }
}
