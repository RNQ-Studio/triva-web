<?php

namespace App\Models;

use App\Models\Concerns\LogsToyotaServiceActivity;
use App\Support\ToyotaServiceWindowRules;
use Database\Factories\ToyotaServiceLocationFactory;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $address
 * @property string $city
 * @property string|null $phone
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $directions_url
 * @property string $timezone
 * @property bool $supports_workshop
 * @property bool $supports_ths
 * @property array<string, list<string>> $operating_hours
 * @property int $confirmation_sla_minutes
 * @property int $cancellation_cutoff_hours
 * @property bool $is_active
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string|null $provenance_url
 * @property Carbon|null $verified_at
 */
class ToyotaServiceLocation extends Model
{
    /** @use HasFactory<ToyotaServiceLocationFactory> */
    use HasFactory, HasUuids, LogsToyotaServiceActivity;

    protected $fillable = [
        'code',
        'name',
        'address',
        'city',
        'phone',
        'latitude',
        'longitude',
        'directions_url',
        'timezone',
        'supports_workshop',
        'supports_ths',
        'operating_hours',
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
            'supports_workshop' => 'boolean',
            'supports_ths' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'operating_hours' => 'array',
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
        static::saving(function (ToyotaServiceLocation $location): void {
            if (! in_array($location->timezone, DateTimeZone::listIdentifiers(), true)) {
                throw ValidationException::withMessages([
                    'timezone' => ['Zona waktu harus berupa identifier IANA yang valid.'],
                ]);
            }

            $hours = $location->operating_hours ?? [];
            for ($day = 1; $day <= 7; $day++) {
                $key = (string) $day;
                $hours[$key] ??= [];
                ToyotaServiceWindowRules::assertValid(
                    $hours[$key],
                    "operating_hours.{$key}",
                );
            }
            $location->operating_hours = $hours;
        });
    }

    /** @param Builder<ToyotaServiceLocation> $query */
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

    /** @return HasMany<ToyotaServiceHoliday, $this> */
    public function holidays(): HasMany
    {
        return $this->hasMany(ToyotaServiceHoliday::class, 'service_location_id');
    }

    /** @return HasMany<ToyotaThsCoverage, $this> */
    public function thsCoverages(): HasMany
    {
        return $this->hasMany(ToyotaThsCoverage::class, 'service_location_id');
    }

    /** @return HasMany<ToyotaServiceBooking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(ToyotaServiceBooking::class, 'service_location_id');
    }

    /** @return list<string> */
    public function timeWindowsFor(Carbon $date): array
    {
        $windows = $this->operating_hours[(string) $date->isoWeekday()] ?? [];

        return $windows;
    }
}
