<?php

namespace App\Models;

use Database\Factories\OtoxpertWorkshopFactory;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property string $id
 * @property string $code
 * @property string|null $partner_code
 * @property string $name
 * @property string $address
 * @property string $province
 * @property string $city
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $phone
 * @property string $timezone
 * @property array<string, list<string>> $operating_hours
 * @property bool $supports_all_vehicle_makes
 * @property bool $supports_pickup_delivery
 * @property int $confirmation_sla_minutes
 * @property int $cancellation_cutoff_hours
 * @property bool $is_active
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string $provenance_url
 * @property Carbon $verified_at
 * @property-read Collection<int, OtoxpertService> $services
 */
class OtoxpertWorkshop extends Model
{
    /** @use HasFactory<OtoxpertWorkshopFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'code',
        'partner_code',
        'name',
        'address',
        'province',
        'city',
        'latitude',
        'longitude',
        'phone',
        'timezone',
        'operating_hours',
        'service_radius_km',
        'supports_all_vehicle_makes',
        'supports_pickup_delivery',
        'confirmation_sla_minutes',
        'cancellation_cutoff_hours',
        'is_active',
        'effective_from',
        'effective_to',
        'provenance_url',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'operating_hours' => 'array',
            'service_radius_km' => 'decimal:2',
            'supports_all_vehicle_makes' => 'boolean',
            'supports_pickup_delivery' => 'boolean',
            'confirmation_sla_minutes' => 'integer',
            'cancellation_cutoff_hours' => 'integer',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (OtoxpertWorkshop $workshop): void {
            if (! in_array($workshop->timezone, DateTimeZone::listIdentifiers(), true)) {
                throw ValidationException::withMessages([
                    'timezone' => ['Zona waktu harus berupa identifier IANA yang valid.'],
                ]);
            }

            $hours = $workshop->operating_hours ?? [];
            for ($day = 1; $day <= 7; $day++) {
                $key = (string) $day;
                $hours[$key] ??= [];
                foreach ($hours[$key] as $window) {
                    if (preg_match('/^\d{2}:\d{2}-\d{2}:\d{2}$/', $window) !== 1) {
                        throw ValidationException::withMessages([
                            "operating_hours.{$key}" => [
                                'Jam operasional harus berformat HH:MM-HH:MM.',
                            ],
                        ]);
                    }
                }
            }
            $workshop->operating_hours = $hours;
        });
    }

    /** @param Builder<OtoxpertWorkshop> $query */
    public function scopeEffective(Builder $query, ?Carbon $onDate = null): void
    {
        $date = ($onDate ?? now($this->timezone ?? 'Asia/Jakarta'))->toDateString();

        $query->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(function (Builder $builder) use ($date): void {
                $builder->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            });
    }

    /** @return BelongsToMany<VehicleMake, $this> */
    public function vehicleMakes(): BelongsToMany
    {
        return $this->belongsToMany(
            VehicleMake::class,
            'otoxpert_workshop_vehicle_makes',
            'workshop_id',
            'vehicle_make_id',
        )->withTimestamps();
    }

    /** @return BelongsToMany<OtoxpertService, $this> */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(
            OtoxpertService::class,
            'otoxpert_workshop_services',
            'workshop_id',
            'service_id',
        )
            ->withPivot(['lead_time_days', 'is_active'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<User, $this> */
    public function operators(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'otoxpert_workshop_operators',
            'workshop_id',
            'user_id',
        )->withPivot('is_active')->withTimestamps();
    }

    /** @return HasMany<OtoxpertWorkshopServicePrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(OtoxpertWorkshopServicePrice::class, 'workshop_id');
    }

    /** @return HasMany<OtoxpertHoliday, $this> */
    public function holidays(): HasMany
    {
        return $this->hasMany(OtoxpertHoliday::class, 'workshop_id');
    }

    /** @return HasMany<OtoxpertBooking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(OtoxpertBooking::class, 'workshop_id');
    }

    public function supportsVehicle(Vehicle $vehicle): bool
    {
        return $this->supports_all_vehicle_makes
            || ($vehicle->vehicle_make_id !== null
                && $this->vehicleMakes()
                    ->whereKey($vehicle->vehicle_make_id)
                    ->exists());
    }

    public function supportsService(OtoxpertService $service): bool
    {
        return $this->services()
            ->whereKey($service->getKey())
            ->wherePivot('is_active', true)
            ->exists();
    }

    public function canBeManagedBy(User $user): bool
    {
        if (! $user->can('service_bookings.update')) {
            return false;
        }

        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return true;
        }

        return $this->operators()
            ->whereKey($user->getKey())
            ->wherePivot('is_active', true)
            ->exists();
    }

    /** @return list<string> */
    public function timeWindowsFor(Carbon $date): array
    {
        return $this->operating_hours[(string) $date->isoWeekday()] ?? [];
    }
}
