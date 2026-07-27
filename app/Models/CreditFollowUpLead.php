<?php

namespace App\Models;

use App\Support\Enums\CreditLeadStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $reference_no
 * @property string $simulation_id
 * @property int $user_id
 * @property int|null $assigned_sales_id
 * @property CreditLeadStatus $status
 * @property string $contact_channel
 * @property string $consent_version
 * @property Carbon $consent_at
 * @property string|null $campaign_source
 * @property string|null $outcome
 * @property string|null $internal_note
 * @property Carbon|null $contacted_at
 * @property Carbon|null $converted_at
 */
class CreditFollowUpLead extends Model
{
    use HasUuids, LogsActivity;

    protected $fillable = [
        'reference_no',
        'simulation_id',
        'user_id',
        'assigned_sales_id',
        'status',
        'contact_channel',
        'consent_version',
        'consent_at',
        'campaign_source',
        'outcome',
        'internal_note',
        'contacted_at',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CreditLeadStatus::class,
            'consent_at' => 'datetime',
            'contacted_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** @return BelongsTo<CreditSimulation, $this> */
    public function simulation(): BelongsTo
    {
        return $this->belongsTo(CreditSimulation::class, 'simulation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedSales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_sales_id');
    }
}
