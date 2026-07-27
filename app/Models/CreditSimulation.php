<?php

namespace App\Models;

use App\Support\Enums\CreditSimulationStatus;
use Database\Factories\CreditSimulationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $reference_no
 * @property int $user_id
 * @property string $credit_program_id
 * @property string|null $appraisal_id
 * @property string|null $comparison_group_id
 * @property CreditSimulationStatus $status
 * @property array<string, mixed> $program_snapshot
 * @property array<string, mixed> $input_snapshot
 * @property array<string, mixed> $calculation_snapshot
 * @property string $formula_version
 * @property int $otr_price
 * @property int $cash_down_payment
 * @property int $trade_in_value
 * @property int $old_vehicle_payoff
 * @property int $trade_in_equity
 * @property bool $use_trade_in_as_dp
 * @property int $approved_discount
 * @property int $total_down_payment
 * @property int $principal
 * @property int $tenor_months
 * @property int $annual_flat_rate_basis_points
 * @property int $total_flat_interest
 * @property int $monthly_installment
 * @property int $administration_fee
 * @property int $provision_fee
 * @property int $upfront_insurance
 * @property int $other_upfront_costs
 * @property int $initial_payment
 * @property int $total_payment
 * @property Carbon|null $valid_until
 * @property string|null $campaign_source
 * @property string $idempotency_key
 * @property string $request_fingerprint
 * @property Carbon $saved_at
 * @property Carbon|null $updated_at
 */
class CreditSimulation extends Model
{
    /** @use HasFactory<CreditSimulationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'reference_no',
        'user_id',
        'credit_program_id',
        'appraisal_id',
        'comparison_group_id',
        'status',
        'program_snapshot',
        'input_snapshot',
        'calculation_snapshot',
        'formula_version',
        'otr_price',
        'cash_down_payment',
        'trade_in_value',
        'old_vehicle_payoff',
        'trade_in_equity',
        'use_trade_in_as_dp',
        'approved_discount',
        'total_down_payment',
        'principal',
        'tenor_months',
        'annual_flat_rate_basis_points',
        'total_flat_interest',
        'monthly_installment',
        'administration_fee',
        'provision_fee',
        'upfront_insurance',
        'other_upfront_costs',
        'initial_payment',
        'total_payment',
        'valid_until',
        'campaign_source',
        'idempotency_key',
        'request_fingerprint',
        'saved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CreditSimulationStatus::class,
            'program_snapshot' => 'array',
            'input_snapshot' => 'array',
            'calculation_snapshot' => 'array',
            'otr_price' => 'integer',
            'cash_down_payment' => 'integer',
            'trade_in_value' => 'integer',
            'old_vehicle_payoff' => 'integer',
            'trade_in_equity' => 'integer',
            'use_trade_in_as_dp' => 'boolean',
            'approved_discount' => 'integer',
            'total_down_payment' => 'integer',
            'principal' => 'integer',
            'tenor_months' => 'integer',
            'annual_flat_rate_basis_points' => 'integer',
            'total_flat_interest' => 'integer',
            'monthly_installment' => 'integer',
            'administration_fee' => 'integer',
            'provision_fee' => 'integer',
            'upfront_insurance' => 'integer',
            'other_upfront_costs' => 'integer',
            'initial_payment' => 'integer',
            'total_payment' => 'integer',
            'valid_until' => 'date',
            'saved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<CreditProgram, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(CreditProgram::class, 'credit_program_id');
    }

    /** @return BelongsTo<Appraisal, $this> */
    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(Appraisal::class);
    }

    /** @return HasOne<CreditFollowUpLead, $this> */
    public function followUpLead(): HasOne
    {
        return $this->hasOne(CreditFollowUpLead::class, 'simulation_id');
    }
}
