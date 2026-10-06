<?php

namespace App\Models;

use Database\Factories\InfoPopupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Popup informasi bergambar di beranda aplikasi (revisi 6 Oktober 2026).
 *
 * Beberapa popup yang tayang bersamaan ditampilkan aplikasi sebagai satu
 * dialog yang bisa digeser, berurutan menurut `sort_order`. Tiap popup
 * muncul lagi setelah `interval_hours` jam sejak terakhir dilihat di
 * perangkat itu.
 *
 * @property string $id
 * @property string $title
 * @property string $image_path
 * @property string|null $button_label
 * @property string|null $button_url
 * @property int $sort_order
 * @property int $interval_hours
 * @property bool $is_active
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class InfoPopup extends Model
{
    /** @use HasFactory<InfoPopupFactory> */
    use HasFactory, HasUuids, LogsActivity;

    public const IMAGE_DIRECTORY = 'info-popups';

    protected $fillable = [
        'title',
        'image_path',
        'button_label',
        'button_url',
        'sort_order',
        'interval_hours',
        'is_active',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'interval_hours' => 'integer',
            'is_active' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('info_popup')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** @param Builder<InfoPopup> $query */
    public function scopeRunning(Builder $query, ?Carbon $onDate = null): void
    {
        $date = ($onDate ?? now('Asia/Jakarta'))->toDateString();

        $query->where('is_active', true)
            ->where(function (Builder $builder) use ($date): void {
                $builder->whereNull('starts_on')
                    ->orWhereDate('starts_on', '<=', $date);
            })
            ->where(function (Builder $builder) use ($date): void {
                $builder->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $date);
            });
    }

    public function isRunning(?Carbon $onDate = null): bool
    {
        $date = ($onDate ?? now('Asia/Jakarta'))->toDateString();

        return $this->is_active
            && ($this->starts_on === null || $this->starts_on->toDateString() <= $date)
            && ($this->ends_on === null || $this->ends_on->toDateString() >= $date);
    }

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }
}
