<?php

namespace App\Models;

use App\Support\Enums\OtoxpertAdminAction;
use App\Support\Enums\OtoxpertBookingStatus;
use App\Support\Enums\ToyotaServiceContactChannel;
use Database\Factories\OtoxpertBookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $reference_no
 * @property int $user_id
 * @property string $vehicle_id
 * @property string $workshop_id
 * @property string $service_id
 * @property int|null $assigned_operator_id
 * @property OtoxpertBookingStatus $status
 * @property int $current_mileage
 * @property Carbon|null $last_service_date
 * @property string $complaint
 * @property list<string> $symptom_codes
 * @property bool $pickup_delivery_requested
 * @property ToyotaServiceContactChannel $contact_channel
 * @property Carbon $primary_start_at
 * @property Carbon $primary_end_at
 * @property Carbon $alternative_start_at
 * @property Carbon $alternative_end_at
 * @property Carbon|null $proposed_start_at
 * @property Carbon|null $proposed_end_at
 * @property string|null $proposal_context
 * @property string|null $proposal_reason
 * @property Carbon|null $proposal_expires_at
 * @property Carbon|null $confirmed_start_at
 * @property Carbon|null $confirmed_end_at
 * @property Carbon|null $reschedule_primary_start_at
 * @property Carbon|null $reschedule_primary_end_at
 * @property Carbon|null $reschedule_alternative_start_at
 * @property Carbon|null $reschedule_alternative_end_at
 * @property string|null $reschedule_reason
 * @property string|null $pic_name
 * @property string|null $arrival_instructions
 * @property string|null $external_booking_number
 * @property string|null $reason_code
 * @property string|null $reason
 * @property int|null $quoted_price_min
 * @property int|null $quoted_price_max
 * @property string|null $quoted_price_type
 * @property string|null $quoted_price_currency
 * @property string|null $quoted_price_source
 * @property Carbon|null $quoted_price_valid_until
 * @property Carbon $partner_consent_at
 * @property string $partner_consent_version
 * @property string|null $campaign_source
 * @property array<string, mixed>|null $campaign_metadata
 * @property string|null $follow_up_outcome
 * @property string $idempotency_key
 * @property string $request_fingerprint
 * @property Carbon $submitted_at
 * @property Carbon $due_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $checked_in_at
 * @property Carbon|null $service_started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon $last_status_changed_at
 * @property Carbon|null $updated_at
 * @property-read OtoxpertWorkshop $workshop
 * @property-read OtoxpertService $service
 */
class OtoxpertBooking extends Model
{
    /** @use HasFactory<OtoxpertBookingFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'reference_no',
        'vehicle_id',
        'workshop_id',
        'service_id',
        'assigned_operator_id',
        'status',
        'current_mileage',
        'last_service_date',
        'complaint',
        'symptom_codes',
        'pickup_delivery_requested',
        'contact_channel',
        'primary_start_at',
        'primary_end_at',
        'alternative_start_at',
        'alternative_end_at',
        'proposed_start_at',
        'proposed_end_at',
        'proposal_context',
        'proposal_reason',
        'proposal_expires_at',
        'confirmed_start_at',
        'confirmed_end_at',
        'reschedule_primary_start_at',
        'reschedule_primary_end_at',
        'reschedule_alternative_start_at',
        'reschedule_alternative_end_at',
        'reschedule_reason',
        'pic_name',
        'arrival_instructions',
        'external_booking_number',
        'reason_code',
        'reason',
        'internal_note',
        'quoted_price_min',
        'quoted_price_max',
        'quoted_price_type',
        'quoted_price_currency',
        'quoted_price_source',
        'quoted_price_valid_until',
        'partner_consent_at',
        'partner_consent_version',
        'campaign_source',
        'campaign_metadata',
        'follow_up_outcome',
        'idempotency_key',
        'request_fingerprint',
        'submitted_at',
        'due_at',
        'confirmed_at',
        'checked_in_at',
        'service_started_at',
        'completed_at',
        'cancelled_at',
        'last_status_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OtoxpertBookingStatus::class,
            'contact_channel' => ToyotaServiceContactChannel::class,
            'current_mileage' => 'integer',
            'symptom_codes' => 'array',
            'pickup_delivery_requested' => 'boolean',
            'last_service_date' => 'date',
            'primary_start_at' => 'datetime',
            'primary_end_at' => 'datetime',
            'alternative_start_at' => 'datetime',
            'alternative_end_at' => 'datetime',
            'proposed_start_at' => 'datetime',
            'proposed_end_at' => 'datetime',
            'proposal_expires_at' => 'datetime',
            'confirmed_start_at' => 'datetime',
            'confirmed_end_at' => 'datetime',
            'reschedule_primary_start_at' => 'datetime',
            'reschedule_primary_end_at' => 'datetime',
            'reschedule_alternative_start_at' => 'datetime',
            'reschedule_alternative_end_at' => 'datetime',
            'quoted_price_min' => 'integer',
            'quoted_price_max' => 'integer',
            'quoted_price_valid_until' => 'date',
            'partner_consent_at' => 'datetime',
            'campaign_metadata' => 'array',
            'submitted_at' => 'datetime',
            'due_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'service_started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_status_changed_at' => 'datetime',
        ];
    }

    /** @param Builder<OtoxpertBooking> $query */
    public function scopeVisibleToStaff(Builder $query, User $user): void
    {
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return;
        }

        $query->whereHas('workshop.operators', function (Builder $operator) use ($user): void {
            $operator->whereKey($user->getKey())
                ->where('otoxpert_workshop_operators.is_active', true);
        });
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

    /** @return BelongsTo<User, $this> */
    public function assignedOperator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_operator_id');
    }

    /** @return HasMany<OtoxpertBookingPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(OtoxpertBookingPhoto::class, 'booking_id');
    }

    /** @return HasMany<OtoxpertBookingStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OtoxpertBookingStatusHistory::class, 'booking_id');
    }

    public function isSlaOverdue(): bool
    {
        return $this->status === OtoxpertBookingStatus::AwaitingConfirmation
            && $this->due_at->isPast();
    }

    public function canCustomerCancel(): bool
    {
        if (! in_array('cancel', $this->status->customerActions(), true)) {
            return false;
        }

        return $this->confirmed_start_at === null
            || now()->addHours($this->workshop->cancellation_cutoff_hours)
                ->lt($this->confirmed_start_at);
    }

    /** @return list<string> */
    public function allowedCustomerActions(): array
    {
        return array_values(array_filter(
            $this->status->customerActions(),
            fn (string $action): bool => $action !== 'cancel'
                || $this->canCustomerCancel(),
        ));
    }

    /** @return list<OtoxpertAdminAction> */
    public function availableAdminActions(): array
    {
        return $this->status->adminActions();
    }
}
