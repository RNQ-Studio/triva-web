<?php

namespace App\Models;

use App\Support\Enums\CreditProgramStatus;
use Database\Factories\CreditProgramFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $program_code
 * @property int $version
 * @property string $partner_name
 * @property string $program_name
 * @property string $city
 * @property string $vehicle_model
 * @property string $vehicle_variant
 * @property int|null $model_year
 * @property int $otr_price
 * @property int $approved_discount
 * @property string|null $package_code
 * @property string|null $unit_key
 * @property string|null $image_path
 * @property int|null $recommended_dp_basis_points
 * @property int $minimum_dp_basis_points
 * @property int $maximum_dp_basis_points
 * @property list<array<string, int|string|null>> $tenor_options
 * @property string $formula_strategy
 * @property string $formula_version
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string $source_reference
 * @property bool $is_demo
 * @property CreditProgramStatus $status
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 */
class CreditProgram extends Model
{
    /** @use HasFactory<CreditProgramFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'program_code',
        'version',
        'partner_name',
        'program_name',
        'city',
        'vehicle_model',
        'vehicle_variant',
        'model_year',
        'otr_price',
        'approved_discount',
        'package_code',
        'unit_key',
        'image_path',
        'recommended_dp_basis_points',
        'minimum_dp_basis_points',
        'maximum_dp_basis_points',
        'tenor_options',
        'formula_strategy',
        'formula_version',
        'effective_from',
        'effective_to',
        'source_reference',
        'is_demo',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'model_year' => 'integer',
            'otr_price' => 'integer',
            'approved_discount' => 'integer',
            'recommended_dp_basis_points' => 'integer',
            'minimum_dp_basis_points' => 'integer',
            'maximum_dp_basis_points' => 'integer',
            'tenor_options' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_demo' => 'boolean',
            'status' => CreditProgramStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (CreditProgram $program): void {
            if (! $program->simulations()->exists()) {
                return;
            }
            $allowed = ['status', 'effective_to', 'updated_at'];
            $protectedChanges = array_diff(
                array_keys($program->getDirty()),
                $allowed,
            );
            if ($protectedChanges !== []) {
                throw ValidationException::withMessages([
                    'program' => [
                        'Program yang sudah dipakai tidak dapat diubah. Nonaktifkan lalu buat versi baru.',
                    ],
                ]);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** @param Builder<CreditProgram> $query */
    public function scopeEffective(Builder $query, ?Carbon $onDate = null): void
    {
        $date = ($onDate ?? now('Asia/Jakarta'))->toDateString();

        $query->where('status', CreditProgramStatus::Approved)
            ->whereDate('effective_from', '<=', $date)
            ->where(function (Builder $builder) use ($date): void {
                $builder->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            });
    }

    /** Gambar unit yang diunggah cabang lewat panel admin. */
    public function imageUrl(): ?string
    {
        if (blank($this->image_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->image_path);
    }

    /** @return array<string, int|string|null>|null */
    public function tenorOption(int $months): ?array
    {
        foreach ($this->tenor_options as $option) {
            if ((int) ($option['tenor_months'] ?? 0) === $months) {
                return $option;
            }
        }

        return null;
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<CreditSimulation, $this> */
    public function simulations(): HasMany
    {
        return $this->hasMany(CreditSimulation::class);
    }
}
