<?php

namespace App\Models;

use App\Support\Enums\AppraisalDecision;
use App\Support\Enums\AppraisalStatus;
use Database\Factories\AppraisalFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $vehicle_id
 * @property string $reference_no
 * @property AppraisalStatus $status
 * @property string|null $idempotency_key
 * @property string|null $tax_status
 * @property string|null $flood_history
 * @property string|null $major_accident_history
 * @property string|null $service_history
 * @property string|null $ownership
 * @property Carbon|null $service_consent_at
 * @property bool $marketing_consent
 * @property int|null $assigned_appraiser_id
 * @property Carbon|null $submitted_at
 * @property Carbon|null $due_at
 * @property AppraisalDecision|null $customer_decision
 * @property Carbon|null $customer_decided_at
 * @property Carbon|null $inspection_scheduled_at
 * @property string|null $inspection_notes
 * @property Carbon|null $updated_at
 * @property-read Vehicle $vehicle
 * @property-read AppraisalResult|null $latestResult
 */
class Appraisal extends Model
{
    /** @use HasFactory<AppraisalFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'reference_no',
        'status',
        'idempotency_key',
        'tax_status',
        'flood_history',
        'major_accident_history',
        'service_history',
        'ownership',
        'service_consent_at',
        'marketing_consent',
        'assigned_appraiser_id',
        'submitted_at',
        'due_at',
        'customer_decision',
        'customer_decided_at',
        'inspection_scheduled_at',
        'inspection_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => AppraisalStatus::class,
            'customer_decision' => AppraisalDecision::class,
            'service_consent_at' => 'datetime',
            'marketing_consent' => 'boolean',
            'submitted_at' => 'datetime',
            'due_at' => 'datetime',
            'customer_decided_at' => 'datetime',
            'inspection_scheduled_at' => 'datetime',
        ];
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

    /** @return BelongsTo<User, $this> */
    public function assignedAppraiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_appraiser_id');
    }

    /** @return HasMany<AppraisalPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(AppraisalPhoto::class);
    }

    /** @return HasMany<AppraisalPhoto, $this> */
    public function currentPhotos(): HasMany
    {
        return $this->photos()->where('is_current', true);
    }

    /** @return HasMany<AppraisalStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(AppraisalStatusHistory::class);
    }

    /** @return HasMany<AppraisalResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(AppraisalResult::class);
    }

    /** @return HasOne<AppraisalResult, $this> */
    public function latestResult(): HasOne
    {
        return $this->hasOne(AppraisalResult::class)->orderByDesc('version');
    }

    public function conditionIsComplete(): bool
    {
        return collect([
            $this->tax_status,
            $this->flood_history,
            $this->major_accident_history,
            $this->service_history,
            $this->ownership,
        ])->every(fn (?string $value): bool => filled($value));
    }
}
