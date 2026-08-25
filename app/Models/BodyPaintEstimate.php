<?php

namespace App\Models;

use App\Support\Enums\BodyPaintAdminAction;
use App\Support\Enums\BodyPaintEstimateStatus;
use Database\Factories\BodyPaintEstimateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $reference_no
 * @property int $user_id
 * @property string $vehicle_id
 * @property string|null $appraisal_id
 * @property string|null $service_location_id
 * @property int|null $assigned_estimator_id
 * @property BodyPaintEstimateStatus $status
 * @property string|null $customer_notes
 * @property string|null $campaign_source
 * @property array<string, mixed>|null $campaign_metadata
 * @property int|null $engine_total_low
 * @property int|null $engine_total_high
 * @property int|null $published_total_low
 * @property int|null $published_total_high
 * @property int|null $published_duration_min_days
 * @property int|null $published_duration_max_days
 * @property int $current_version
 * @property bool $has_high_risk_damage
 * @property bool $requires_physical_inspection
 * @property string $idempotency_key
 * @property string $request_fingerprint
 * @property Carbon|null $submitted_at
 * @property Carbon|null $due_at
 * @property Carbon|null $published_at
 * @property Carbon|null $valid_until
 * @property Carbon|null $accepted_at
 * @property Carbon|null $declined_at
 * @property Carbon|null $cancelled_at
 * @property Carbon $last_status_changed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Vehicle $vehicle
 * @property-read Appraisal|null $appraisal
 * @property-read ToyotaServiceLocation|null $serviceLocation
 * @property-read User|null $assignedEstimator
 * @property-read Collection<int, BodyPaintEstimateDamage> $damages
 * @property-read Collection<int, BodyPaintDamagePhoto> $photos
 * @property-read Collection<int, BodyPaintEstimateItem> $items
 * @property-read Collection<int, BodyPaintEstimateVersion> $versions
 * @property-read BodyPaintEstimateVersion|null $currentPublishedVersion
 * @property-read Collection<int, BodyPaintStatusHistory> $statusHistories
 * @property-read ToyotaServiceBooking|null $booking
 */
class BodyPaintEstimate extends Model
{
    /** @use HasFactory<BodyPaintEstimateFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'reference_no',
        'status',
        'customer_notes',
        'is_insured',
        'insurance_provider',
        'campaign_source',
        'campaign_metadata',
        'assigned_estimator_id',
        'engine_total_low',
        'engine_total_high',
        'published_total_low',
        'published_total_high',
        'published_duration_min_days',
        'published_duration_max_days',
        'current_version',
        'has_high_risk_damage',
        'requires_physical_inspection',
        'idempotency_key',
        'request_fingerprint',
        'submitted_at',
        'due_at',
        'published_at',
        'valid_until',
        'accepted_at',
        'declined_at',
        'cancelled_at',
        'last_status_changed_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('body_paint_estimate')
            ->logOnly([
                'status',
                'assigned_estimator_id',
                'engine_total_low',
                'engine_total_high',
                'published_total_low',
                'published_total_high',
                'current_version',
                'submitted_at',
                'published_at',
                'valid_until',
                'accepted_at',
                'declined_at',
                'cancelled_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'status' => BodyPaintEstimateStatus::class,
            'is_insured' => 'boolean',
            'campaign_metadata' => 'array',
            'engine_total_low' => 'integer',
            'engine_total_high' => 'integer',
            'published_total_low' => 'integer',
            'published_total_high' => 'integer',
            'published_duration_min_days' => 'integer',
            'published_duration_max_days' => 'integer',
            'current_version' => 'integer',
            'has_high_risk_damage' => 'boolean',
            'requires_physical_inspection' => 'boolean',
            'submitted_at' => 'datetime',
            'due_at' => 'datetime',
            'published_at' => 'datetime',
            'valid_until' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_status_changed_at' => 'datetime',
        ];
    }

    /** @param Builder<BodyPaintEstimate> $query */
    public function scopeVisibleToStaff(Builder $query, User $user): void
    {
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return;
        }

        $query->where('assigned_estimator_id', $user->getKey());
    }

    /** @return list<BodyPaintAdminAction> */
    public function availableAdminActions(): array
    {
        return match ($this->status) {
            BodyPaintEstimateStatus::Submitted,
            BodyPaintEstimateStatus::AutoEstimated,
            BodyPaintEstimateStatus::ManualReview => [
                BodyPaintAdminAction::Assign,
                BodyPaintAdminAction::StartReview,
            ],
            BodyPaintEstimateStatus::UnderEstimatorReview => [
                BodyPaintAdminAction::Assign,
                BodyPaintAdminAction::RequestPhotos,
                BodyPaintAdminAction::Publish,
            ],
            BodyPaintEstimateStatus::EstimateReady,
            BodyPaintEstimateStatus::Accepted,
            BodyPaintEstimateStatus::BookingRequested => [
                BodyPaintAdminAction::Publish,
                BodyPaintAdminAction::ScheduleInspection,
            ],
            default => [],
        };
    }

    public function isSlaOverdue(): bool
    {
        return in_array($this->status, [
            BodyPaintEstimateStatus::Submitted,
            BodyPaintEstimateStatus::AutoEstimated,
            BodyPaintEstimateStatus::ManualReview,
        ], true)
            && $this->due_at?->isPast() === true;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<Appraisal, $this> */
    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(Appraisal::class);
    }

    /** @return BelongsTo<ToyotaServiceLocation, $this> */
    public function serviceLocation(): BelongsTo
    {
        return $this->belongsTo(ToyotaServiceLocation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedEstimator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_estimator_id');
    }

    /** @return HasMany<BodyPaintEstimateDamage, $this> */
    public function damages(): HasMany
    {
        return $this->hasMany(
            BodyPaintEstimateDamage::class,
            'estimate_id',
        )->orderBy('sort_order');
    }

    /** @return HasMany<BodyPaintDamagePhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(BodyPaintDamagePhoto::class, 'estimate_id');
    }

    /** @return HasMany<BodyPaintEstimateItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BodyPaintEstimateItem::class, 'estimate_id')
            ->orderBy('sort_order');
    }

    /** @return HasMany<BodyPaintEstimateVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(BodyPaintEstimateVersion::class, 'estimate_id')
            ->orderByDesc('version');
    }

    /** @return HasOne<BodyPaintEstimateVersion, $this> */
    public function currentPublishedVersion(): HasOne
    {
        return $this->hasOne(BodyPaintEstimateVersion::class, 'estimate_id')
            ->orderByDesc('version');
    }

    /** @return HasMany<BodyPaintStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(BodyPaintStatusHistory::class, 'estimate_id')
            ->oldest('created_at');
    }

    /** @return HasOne<ToyotaServiceBooking, $this> */
    public function booking(): HasOne
    {
        return $this->hasOne(
            ToyotaServiceBooking::class,
            'source_bp_estimate_id',
        );
    }
}
