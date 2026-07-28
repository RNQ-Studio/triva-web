<?php

namespace App\Models;

use App\Support\Enums\MarketDataSourceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property MarketDataSourceStatus $status
 * @property string $base_url
 * @property string|null $approval_reference
 * @property Carbon|null $approved_at
 * @property Carbon|null $approval_expires_at
 * @property int $rate_limit_per_minute
 * @property int $retention_days
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $last_success_at
 * @property Carbon|null $last_failure_at
 * @property string|null $last_error_code
 */
class MarketDataSource extends Model
{
    protected $fillable = [
        'name',
        'status',
        'base_url',
        'approval_reference',
        'approved_at',
        'approval_expires_at',
        'rate_limit_per_minute',
        'retention_days',
        'settings',
        'last_synced_at',
        'last_success_at',
        'last_failure_at',
        'last_error_code',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $source): void {
            if ($source->status !== MarketDataSourceStatus::Active) {
                return;
            }

            if (
                blank($source->approval_reference)
                || $source->approved_at === null
                || $source->approval_expires_at === null
                || $source->approval_expires_at->isPast()
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Provider hanya dapat diaktifkan dengan bukti izin dan masa berlaku yang valid.',
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => MarketDataSourceStatus::class,
            'approved_at' => 'datetime',
            'approval_expires_at' => 'datetime',
            'rate_limit_per_minute' => 'integer',
            'retention_days' => 'integer',
            'settings' => 'array',
            'last_synced_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
        ];
    }

    public function isEligible(): bool
    {
        return $this->status === MarketDataSourceStatus::Active
            && filled($this->approval_reference)
            && $this->approved_at !== null
            && $this->approval_expires_at?->isFuture() === true;
    }

    /** @return HasMany<AppraisalMarketComparable, $this> */
    public function marketComparables(): HasMany
    {
        return $this->hasMany(AppraisalMarketComparable::class);
    }

    /** @return HasMany<AppraisalAiAgentRun, $this> */
    public function aiAgentRuns(): HasMany
    {
        return $this->hasMany(AppraisalAiAgentRun::class);
    }
}
