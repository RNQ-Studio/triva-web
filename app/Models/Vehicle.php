<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property int $user_id
 * @property int|null $vehicle_make_id
 * @property string $make
 * @property string $model
 * @property string $variant
 * @property int $year
 * @property string $transmission
 * @property string $fuel_type
 * @property int $mileage
 * @property string $color
 * @property string $license_plate
 * @property int|null $province_id
 * @property int|null $city_id
 * @property string $city
 */
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'vehicle_make_id',
        'make',
        'model',
        'variant',
        'year',
        'transmission',
        'fuel_type',
        'mileage',
        'color',
        'license_plate',
        'province_id',
        'city_id',
        'city',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'mileage' => 'integer',
            'vehicle_make_id' => 'integer',
            'province_id' => 'integer',
            'city_id' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<VehicleMake, $this> */
    public function vehicleMake(): BelongsTo
    {
        return $this->belongsTo(VehicleMake::class);
    }

    /** @return BelongsTo<Region, $this> */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'province_id');
    }

    /** @return BelongsTo<Region, $this> */
    public function cityRegion(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'city_id');
    }

    /** @return HasMany<Appraisal, $this> */
    public function appraisals(): HasMany
    {
        return $this->hasMany(Appraisal::class);
    }
}
