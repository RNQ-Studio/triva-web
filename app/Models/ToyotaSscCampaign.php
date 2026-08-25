<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $campaign_code
 * @property string $title
 * @property string|null $description
 * @property string|null $vehicle_model
 * @property int|null $year_from
 * @property int|null $year_to
 * @property list<string>|null $vin_prefixes
 * @property string|null $recommended_action
 * @property bool $is_active
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string $source_reference
 */
class ToyotaSscCampaign extends Model
{
    use HasUuids, LogsActivity;

    protected $fillable = [
        'campaign_code',
        'title',
        'description',
        'vehicle_model',
        'year_from',
        'year_to',
        'vin_prefixes',
        'recommended_action',
        'is_active',
        'effective_from',
        'effective_to',
        'source_reference',
    ];

    protected function casts(): array
    {
        return [
            'year_from' => 'integer',
            'year_to' => 'integer',
            'vin_prefixes' => 'array',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('toyota_ssc_campaign')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** @param Builder<ToyotaSscCampaign> $query */
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

    /**
     * Apakah satu nomor rangka tercakup kampanye ini.
     *
     * Kampanye tanpa daftar awalan berlaku untuk seluruh unit pada model dan
     * rentang tahun yang ditetapkan; tahun kendaraan diperiksa hanya bila
     * pelanggan memang mengisinya.
     */
    public function covers(string $vin, ?int $year): bool
    {
        $normalized = $this->normalizeVin($vin);
        if ($normalized === '') {
            return false;
        }

        if ($year !== null) {
            if ($this->year_from !== null && $year < $this->year_from) {
                return false;
            }

            if ($this->year_to !== null && $year > $this->year_to) {
                return false;
            }
        }

        $prefixes = $this->vin_prefixes ?? [];
        if ($prefixes === []) {
            // Tanpa daftar awalan, model dan tahun sajalah penentunya. Kampanye
            // yang juga tidak menyebut model terlalu luas untuk dipakai
            // menyimpulkan apa pun tentang satu unit.
            return $this->vehicle_model !== null;
        }

        foreach ($prefixes as $prefix) {
            $candidate = $this->normalizeVin((string) $prefix);
            if ($candidate !== '' && str_starts_with($normalized, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeVin(string $value): string
    {
        return (string) Str::of($value)->replaceMatches('/[^A-Za-z0-9]/', '')->upper();
    }
}
