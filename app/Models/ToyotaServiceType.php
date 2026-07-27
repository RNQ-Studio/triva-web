<?php

namespace App\Models;

use App\Models\Concerns\LogsToyotaServiceActivity;
use App\Support\Enums\ToyotaServiceFulfillmentType;
use Database\Factories\ToyotaServiceTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $supports_workshop
 * @property bool $supports_ths
 * @property int $workshop_lead_time_days
 * @property int $ths_lead_time_days
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 */
class ToyotaServiceType extends Model
{
    /** @use HasFactory<ToyotaServiceTypeFactory> */
    use HasFactory, HasUuids, LogsToyotaServiceActivity;

    protected $fillable = [
        'code',
        'name',
        'description',
        'supports_workshop',
        'supports_ths',
        'workshop_lead_time_days',
        'ths_lead_time_days',
        'sort_order',
        'is_active',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'supports_workshop' => 'boolean',
            'supports_ths' => 'boolean',
            'workshop_lead_time_days' => 'integer',
            'ths_lead_time_days' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /** @param Builder<ToyotaServiceType> $query */
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

    public function supports(ToyotaServiceFulfillmentType $fulfillmentType): bool
    {
        return match ($fulfillmentType) {
            ToyotaServiceFulfillmentType::Workshop => $this->supports_workshop,
            ToyotaServiceFulfillmentType::Ths => $this->supports_ths,
        };
    }

    public function leadTimeDays(ToyotaServiceFulfillmentType $fulfillmentType): int
    {
        return match ($fulfillmentType) {
            ToyotaServiceFulfillmentType::Workshop => $this->workshop_lead_time_days,
            ToyotaServiceFulfillmentType::Ths => $this->ths_lead_time_days,
        };
    }

    /** @return HasMany<ToyotaServiceBooking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(ToyotaServiceBooking::class, 'service_type_id');
    }
}
