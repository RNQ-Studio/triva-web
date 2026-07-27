<?php

namespace App\Models;

use Database\Factories\OtoxpertServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $default_lead_time_days
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 */
class OtoxpertService extends Model
{
    /** @use HasFactory<OtoxpertServiceFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'code',
        'name',
        'description',
        'default_lead_time_days',
        'sort_order',
        'is_active',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'default_lead_time_days' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /** @param Builder<OtoxpertService> $query */
    public function scopeEffective(Builder $query, ?Carbon $onDate = null): void
    {
        $date = ($onDate ?? now('Asia/Jakarta'))->toDateString();

        $query->where('otoxpert_services.is_active', true)
            ->whereDate('otoxpert_services.effective_from', '<=', $date)
            ->where(function (Builder $builder) use ($date): void {
                $builder->whereNull('otoxpert_services.effective_to')
                    ->orWhereDate('otoxpert_services.effective_to', '>=', $date);
            });
    }

    /** @return BelongsToMany<OtoxpertWorkshop, $this> */
    public function workshops(): BelongsToMany
    {
        return $this->belongsToMany(
            OtoxpertWorkshop::class,
            'otoxpert_workshop_services',
            'service_id',
            'workshop_id',
        )
            ->withPivot(['lead_time_days', 'is_active'])
            ->withTimestamps();
    }

    /** @return HasMany<OtoxpertWorkshopServicePrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(OtoxpertWorkshopServicePrice::class, 'service_id');
    }

    /** @return HasMany<OtoxpertBooking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(OtoxpertBooking::class, 'service_id');
    }
}
