<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $workshop_id
 * @property string $service_id
 * @property string $price_type
 * @property int $minimum_amount
 * @property int|null $maximum_amount
 * @property string $currency
 * @property array<int, string>|null $included_items
 * @property array<int, string>|null $excluded_items
 * @property string $disclaimer
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property bool $is_active
 * @property string $source_url
 * @property Carbon $verified_at
 */
class OtoxpertWorkshopServicePrice extends Model
{
    use HasUuids;

    protected $fillable = [
        'workshop_id',
        'service_id',
        'price_type',
        'minimum_amount',
        'maximum_amount',
        'currency',
        'included_items',
        'excluded_items',
        'disclaimer',
        'effective_from',
        'effective_to',
        'is_active',
        'source_url',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'minimum_amount' => 'integer',
            'maximum_amount' => 'integer',
            'included_items' => 'array',
            'excluded_items' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    /** @param Builder<OtoxpertWorkshopServicePrice> $query */
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

    /** @return BelongsTo<OtoxpertWorkshop, $this> */
    public function workshop(): BelongsTo
    {
        return $this->belongsTo(OtoxpertWorkshop::class, 'workshop_id');
    }

    /** @return BelongsTo<OtoxpertService, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(OtoxpertService::class, 'service_id');
    }
}
