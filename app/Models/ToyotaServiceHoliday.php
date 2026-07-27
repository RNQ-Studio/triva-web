<?php

namespace App\Models;

use App\Models\Concerns\LogsToyotaServiceActivity;
use App\Support\ToyotaServiceWindowRules;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property string $id
 * @property string|null $service_location_id
 * @property Carbon $holiday_date
 * @property string $name
 * @property bool $is_closed
 * @property list<string>|null $time_windows
 */
class ToyotaServiceHoliday extends Model
{
    use HasUuids, LogsToyotaServiceActivity;

    protected $fillable = [
        'service_location_id',
        'holiday_date',
        'name',
        'is_closed',
        'time_windows',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'is_closed' => 'boolean',
            'time_windows' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ToyotaServiceHoliday $holiday): void {
            if (! $holiday->is_closed && empty($holiday->time_windows)) {
                throw ValidationException::withMessages([
                    'time_windows' => [
                        'Jam pengganti wajib diisi ketika lokasi tidak tutup penuh.',
                    ],
                ]);
            }
            ToyotaServiceWindowRules::assertValid(
                $holiday->time_windows,
                'time_windows',
            );
        });
    }

    /** @return BelongsTo<ToyotaServiceLocation, $this> */
    public function serviceLocation(): BelongsTo
    {
        return $this->belongsTo(ToyotaServiceLocation::class, 'service_location_id');
    }
}
