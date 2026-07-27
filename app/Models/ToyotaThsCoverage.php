<?php

namespace App\Models;

use App\Models\Concerns\LogsToyotaServiceActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $service_location_id
 * @property string $city
 * @property string|null $latitude_min
 * @property string|null $latitude_max
 * @property string|null $longitude_min
 * @property string|null $longitude_max
 * @property bool $is_active
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string $verification_source
 */
class ToyotaThsCoverage extends Model
{
    use HasUuids, LogsToyotaServiceActivity;

    protected $fillable = [
        'service_location_id',
        'city',
        'latitude_min',
        'latitude_max',
        'longitude_min',
        'longitude_max',
        'is_active',
        'effective_from',
        'effective_to',
        'verification_source',
    ];

    protected function casts(): array
    {
        return [
            'latitude_min' => 'decimal:7',
            'latitude_max' => 'decimal:7',
            'longitude_min' => 'decimal:7',
            'longitude_max' => 'decimal:7',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /** @param Builder<ToyotaThsCoverage> $query */
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

    /** @param Builder<ToyotaThsCoverage> $query */
    public function scopeOperational(Builder $query, ?Carbon $onDate = null): void
    {
        $query->effective($onDate)
            ->whereNotNull('latitude_min')
            ->whereNotNull('latitude_max')
            ->whereNotNull('longitude_min')
            ->whereNotNull('longitude_max');
    }

    /** @return BelongsTo<ToyotaServiceLocation, $this> */
    public function serviceLocation(): BelongsTo
    {
        return $this->belongsTo(ToyotaServiceLocation::class, 'service_location_id');
    }

    public function containsCoordinates(float $latitude, float $longitude): bool
    {
        if (
            $this->latitude_min === null
            || $this->latitude_max === null
            || $this->longitude_min === null
            || $this->longitude_max === null
        ) {
            return false;
        }

        return $latitude >= (float) $this->latitude_min
            && $latitude <= (float) $this->latitude_max
            && $longitude >= (float) $this->longitude_min
            && $longitude <= (float) $this->longitude_max;
    }
}
