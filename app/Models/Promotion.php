<?php

namespace App\Models;

use App\Support\Enums\PromotionCategory;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property PromotionCategory $category
 * @property string $title
 * @property string|null $subtitle
 * @property string|null $description
 * @property string|null $image_path
 * @property string|null $cta_label
 * @property string|null $cta_url
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $show_as_popup
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 */
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'category',
        'title',
        'subtitle',
        'description',
        'image_path',
        'cta_label',
        'cta_url',
        'sort_order',
        'is_active',
        'show_as_popup',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'category' => PromotionCategory::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'show_as_popup' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('promotion')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** @param Builder<Promotion> $query */
    public function scopeRunning(Builder $query, ?Carbon $onDate = null): void
    {
        $date = ($onDate ?? now('Asia/Jakarta'))->toDateString();

        $query->where('is_active', true)
            ->whereDate('starts_on', '<=', $date)
            ->where(function (Builder $builder) use ($date): void {
                $builder->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $date);
            });
    }

    public function imageUrl(): ?string
    {
        if (blank($this->image_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->image_path);
    }
}
