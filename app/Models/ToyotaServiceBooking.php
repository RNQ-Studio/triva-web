<?php

namespace App\Models;

use App\Models\Concerns\LogsToyotaServiceActivity;
use App\Support\Enums\ToyotaServiceAdminAction;
use App\Support\Enums\ToyotaServiceBookingStatus;
use App\Support\Enums\ToyotaServiceContactChannel;
use App\Support\Enums\ToyotaServiceFulfillmentType;
use Database\Factories\ToyotaServiceBookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;

/**
 * @property string $id
 * @property string $reference_no
 * @property int $user_id
 * @property string $vehicle_id
 * @property string $service_location_id
 * @property string $service_type_id
 * @property ToyotaServiceFulfillmentType $fulfillment_type
 * @property ToyotaServiceBookingStatus $status
 * @property string $idempotency_key
 * @property string $idempotency_fingerprint
 * @property int $current_mileage
 * @property string $complaint
 * @property Carbon $primary_start_at
 * @property Carbon $primary_end_at
 * @property Carbon $alternative_start_at
 * @property Carbon $alternative_end_at
 * @property Carbon $active_slot_start_at
 * @property Carbon $active_slot_end_at
 * @property Carbon|null $proposed_start_at
 * @property Carbon|null $proposed_end_at
 * @property string|null $proposal_context
 * @property string|null $proposal_reason
 * @property Carbon|null $proposal_expires_at
 * @property string|null $proposed_pic_name
 * @property string|null $proposed_arrival_instructions
 * @property string|null $proposed_external_booking_number
 * @property Carbon|null $confirmed_start_at
 * @property Carbon|null $confirmed_end_at
 * @property Carbon|null $reschedule_primary_start_at
 * @property Carbon|null $reschedule_primary_end_at
 * @property Carbon|null $reschedule_alternative_start_at
 * @property Carbon|null $reschedule_alternative_end_at
 * @property string|null $reschedule_reason
 * @property string|null $ths_address
 * @property string|null $ths_city
 * @property string|null $ths_latitude
 * @property string|null $ths_longitude
 * @property string|null $ths_location_notes
 * @property ToyotaServiceContactChannel $contact_channel
 * @property int|null $assigned_service_advisor_id
 * @property string|null $pic_name
 * @property string|null $arrival_instructions
 * @property string|null $external_booking_number
 * @property string|null $reason_code
 * @property string|null $reason
 * @property string|null $source_appraisal_id
 * @property string|null $source_bp_estimate_id
 * @property string|null $campaign_source
 * @property array<string, mixed>|null $campaign_metadata
 * @property Carbon $submitted_at
 * @property Carbon $due_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon $last_status_changed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Vehicle $vehicle
 * @property-read ToyotaServiceLocation $serviceLocation
 * @property-read ToyotaServiceType $serviceType
 */
class ToyotaServiceBooking extends Model
{
    /** @use HasFactory<ToyotaServiceBookingFactory> */
    use HasFactory, HasUuids, LogsToyotaServiceActivity;

    protected $fillable = [
        'reference_no',
        'fulfillment_type',
        'status',
        'idempotency_key',
        'idempotency_fingerprint',
        'current_mileage',
        'complaint',
        'primary_start_at',
        'primary_end_at',
        'alternative_start_at',
        'alternative_end_at',
        'active_slot_start_at',
        'active_slot_end_at',
        'proposed_start_at',
        'proposed_end_at',
        'proposal_context',
        'proposal_reason',
        'proposal_expires_at',
        'proposed_pic_name',
        'proposed_arrival_instructions',
        'proposed_external_booking_number',
        'confirmed_start_at',
        'confirmed_end_at',
        'reschedule_primary_start_at',
        'reschedule_primary_end_at',
        'reschedule_alternative_start_at',
        'reschedule_alternative_end_at',
        'reschedule_reason',
        'ths_address',
        'ths_city',
        'ths_latitude',
        'ths_longitude',
        'ths_location_notes',
        'contact_channel',
        'assigned_service_advisor_id',
        'pic_name',
        'arrival_instructions',
        'external_booking_number',
        'reason_code',
        'reason',
        'source_appraisal_id',
        'source_bp_estimate_id',
        'campaign_source',
        'campaign_metadata',
        'submitted_at',
        'due_at',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
        'last_status_changed_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('toyota_service')
            ->logOnly([
                'status',
                'user_id',
                'vehicle_id',
                'service_location_id',
                'service_type_id',
                'fulfillment_type',
                'assigned_service_advisor_id',
                'active_slot_start_at',
                'active_slot_end_at',
                'proposed_start_at',
                'proposed_end_at',
                'proposal_context',
                'proposal_expires_at',
                'confirmed_start_at',
                'confirmed_end_at',
                'reason_code',
                'submitted_at',
                'due_at',
                'confirmed_at',
                'completed_at',
                'cancelled_at',
                'last_status_changed_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'fulfillment_type' => ToyotaServiceFulfillmentType::class,
            'status' => ToyotaServiceBookingStatus::class,
            'contact_channel' => ToyotaServiceContactChannel::class,
            'current_mileage' => 'integer',
            'primary_start_at' => 'datetime',
            'primary_end_at' => 'datetime',
            'alternative_start_at' => 'datetime',
            'alternative_end_at' => 'datetime',
            'active_slot_start_at' => 'datetime',
            'active_slot_end_at' => 'datetime',
            'proposed_start_at' => 'datetime',
            'proposed_end_at' => 'datetime',
            'proposal_expires_at' => 'datetime',
            'confirmed_start_at' => 'datetime',
            'confirmed_end_at' => 'datetime',
            'reschedule_primary_start_at' => 'datetime',
            'reschedule_primary_end_at' => 'datetime',
            'reschedule_alternative_start_at' => 'datetime',
            'reschedule_alternative_end_at' => 'datetime',
            'ths_latitude' => 'decimal:7',
            'ths_longitude' => 'decimal:7',
            'campaign_metadata' => 'array',
            'submitted_at' => 'datetime',
            'due_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_status_changed_at' => 'datetime',
        ];
    }

    /** @param Builder<ToyotaServiceBooking> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', collect(ToyotaServiceBookingStatus::cases())
            ->filter(fn (ToyotaServiceBookingStatus $status): bool => $status->isActive())
            ->map(fn (ToyotaServiceBookingStatus $status): string => $status->value));
    }

    public function hasPendingReschedule(): bool
    {
        return $this->confirmed_start_at !== null
            && $this->confirmed_end_at !== null
            && (
                $this->status === ToyotaServiceBookingStatus::RescheduleRequested
                || (
                    $this->status === ToyotaServiceBookingStatus::AlternativeProposed
                    && $this->proposal_context === 'reschedule'
                )
            );
    }

    /** @return list<ToyotaServiceAdminAction> */
    public function availableAdminActions(?Carbon $at = null): array
    {
        $at ??= now();
        $actions = $this->status->adminActions();

        if ($this->hasPendingReschedule()) {
            $actions = [
                ToyotaServiceAdminAction::Assign,
                ToyotaServiceAdminAction::ProposeAlternative,
                ToyotaServiceAdminAction::ConfirmReschedule,
                ToyotaServiceAdminAction::CheckIn,
                ToyotaServiceAdminAction::Cancel,
                ToyotaServiceAdminAction::VerifyBenefit,
            ];
        }

        if (
            in_array($this->status, [
                ToyotaServiceBookingStatus::Confirmed,
                ToyotaServiceBookingStatus::AlternativeProposed,
                ToyotaServiceBookingStatus::RescheduleRequested,
            ], true)
            && $this->confirmed_end_at !== null
            && $at->greaterThanOrEqualTo($this->confirmed_end_at)
        ) {
            $actions[] = ToyotaServiceAdminAction::MarkNoShow;
        } else {
            $actions = array_values(array_filter(
                $actions,
                fn (ToyotaServiceAdminAction $action): bool => $action
                    !== ToyotaServiceAdminAction::MarkNoShow,
            ));
        }

        if (
            $this->reschedule_primary_start_at === null
            || $this->reschedule_primary_end_at === null
            || $this->reschedule_alternative_start_at === null
            || $this->reschedule_alternative_end_at === null
        ) {
            $actions = array_values(array_filter(
                $actions,
                fn (ToyotaServiceAdminAction $action): bool => $action
                    !== ToyotaServiceAdminAction::ConfirmReschedule,
            ));
        }

        $unique = [];
        foreach ($actions as $action) {
            $unique[$action->value] = $action;
        }

        return array_values($unique);
    }

    /** @param Builder<ToyotaServiceBooking> $query */
    public function scopeForLocalDate(Builder $query, string $localDate): void
    {
        self::constrainToLocalDate($query, $localDate);
    }

    /**
     * @param  Builder<ToyotaServiceBooking>  $query
     * @return Builder<ToyotaServiceBooking>
     */
    public static function constrainToLocalDate(Builder $query, string $localDate): Builder
    {
        $query->whereExists(function ($locationQuery) use ($localDate): void {
            $locationQuery
                ->selectRaw('1')
                ->from('toyota_service_locations as local_date_location')
                ->whereColumn(
                    'local_date_location.id',
                    'toyota_service_bookings.service_location_id',
                )
                ->whereRaw(
                    '(toyota_service_bookings.active_slot_start_at AT TIME ZONE local_date_location.timezone)::date = ?',
                    [$localDate],
                );
        });

        return $query;
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

    /** @return BelongsTo<ToyotaServiceLocation, $this> */
    public function serviceLocation(): BelongsTo
    {
        return $this->belongsTo(ToyotaServiceLocation::class, 'service_location_id');
    }

    /** @return BelongsTo<ToyotaServiceType, $this> */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ToyotaServiceType::class, 'service_type_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedServiceAdvisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_service_advisor_id');
    }

    /** @return BelongsTo<Appraisal, $this> */
    public function sourceAppraisal(): BelongsTo
    {
        return $this->belongsTo(Appraisal::class, 'source_appraisal_id');
    }

    /** @return BelongsTo<BodyPaintEstimate, $this> */
    public function sourceBodyPaintEstimate(): BelongsTo
    {
        return $this->belongsTo(
            BodyPaintEstimate::class,
            'source_bp_estimate_id',
        );
    }

    /** @return HasMany<ToyotaServiceBookingPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(ToyotaServiceBookingPhoto::class, 'service_booking_id');
    }

    /** @return HasMany<ToyotaServiceBookingStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(ToyotaServiceBookingStatusHistory::class, 'service_booking_id');
    }

    /** @return HasMany<VehicleBenefitCheck, $this> */
    public function benefitChecks(): HasMany
    {
        return $this->hasMany(VehicleBenefitCheck::class, 'service_booking_id')
            ->orderByRaw(
                "CASE benefit_type
                    WHEN 't_care' THEN 1
                    WHEN 'ssc' THEN 2
                    WHEN 'warranty' THEN 3
                    ELSE 4
                END"
            );
    }

    public function isSlaOverdue(): bool
    {
        return $this->status === ToyotaServiceBookingStatus::AwaitingConfirmation
            && $this->due_at->isPast();
    }

    public function canCustomerCancel(): bool
    {
        if (! in_array('cancel', $this->status->customerActions(), true)) {
            return false;
        }

        $cutoffHours = $this->serviceLocation->cancellation_cutoff_hours;
        $confirmedStart = $this->confirmed_start_at;

        return $confirmedStart === null || now()->addHours($cutoffHours)->lt($confirmedStart);
    }

    /** @return list<string> */
    public function allowedCustomerActions(): array
    {
        return array_values(array_filter(
            $this->status->customerActions(),
            fn (string $action): bool => $action !== 'cancel' || $this->canCustomerCancel(),
        ));
    }
}
