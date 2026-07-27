<?php

namespace App\Services;

use App\Exceptions\CreditSimulationConflictException;
use App\Models\CreditProgram;
use App\Models\CreditSimulation;
use App\Models\User;
use App\Support\Enums\CreditSimulationStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreditSimulationCreationService
{
    public function __construct(
        private readonly CreditSimulationCalculator $calculator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{simulation: CreditSimulation, replayed: bool}
     */
    public function create(User $user, array $data): array
    {
        $fingerprint = $this->fingerprint($data);
        $existing = CreditSimulation::query()
            ->where('user_id', $user->getKey())
            ->where('idempotency_key', $data['idempotency_key'])
            ->first();
        if ($existing !== null) {
            if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
                throw new CreditSimulationConflictException(
                    'Idempotency-Key sudah dipakai untuk payload berbeda.',
                    'CREDIT_IDEMPOTENCY_CONFLICT',
                );
            }

            return [
                'simulation' => $this->load($existing),
                'replayed' => true,
            ];
        }

        $program = CreditProgram::query()->findOrFail($data['program_id']);
        $result = $this->calculator->calculate($user, $program, $data);

        try {
            $simulation = DB::transaction(function () use (
                $user,
                $program,
                $data,
                $result,
                $fingerprint,
            ): CreditSimulation {
                User::query()
                    ->whereKey($user)
                    ->lockForUpdate()
                    ->firstOrFail();
                $groupId = $data['comparison_group_id'] ?? null;
                if (is_string($groupId)) {
                    $groupQuery = CreditSimulation::query()
                        ->where('user_id', $user->getKey())
                        ->where('comparison_group_id', $groupId);
                    $group = (clone $groupQuery)
                        ->lockForUpdate()
                        ->get(['id']);
                    if ($group->count() >= 3) {
                        throw ValidationException::withMessages([
                            'comparison_group_id' => [
                                'Maksimal tiga skenario dalam satu perbandingan.',
                            ],
                        ]);
                    }
                    $inputs = $result['inputs'];
                    if ((clone $groupQuery)
                        ->where(
                            'credit_program_id',
                            $program->getKey(),
                        )
                        ->where(
                            'appraisal_id',
                            $inputs['trade_in_appraisal_id'],
                        )
                        ->where(
                            'cash_down_payment',
                            $inputs['cash_down_payment'],
                        )
                        ->where('trade_in_value', $inputs['trade_in_value'])
                        ->where(
                            'use_trade_in_as_dp',
                            $inputs['use_trade_in_as_dp'],
                        )
                        ->where(
                            'old_vehicle_payoff',
                            $inputs['old_vehicle_payoff'],
                        )
                        ->where('tenor_months', $inputs['tenor_months'])
                        ->exists()) {
                        throw ValidationException::withMessages([
                            'comparison_group_id' => [
                                'Skenario yang sama sudah ada dalam perbandingan.',
                            ],
                        ]);
                    }
                }

                $inputs = $result['inputs'];
                $calculation = $result['calculation'];

                return CreditSimulation::query()->create([
                    'reference_no' => $this->referenceNumber(),
                    'user_id' => $user->getKey(),
                    'credit_program_id' => $program->getKey(),
                    'appraisal_id' => $inputs['trade_in_appraisal_id'],
                    'comparison_group_id' => $groupId,
                    'status' => CreditSimulationStatus::Saved,
                    'program_snapshot' => $result['program'],
                    'input_snapshot' => $inputs,
                    'calculation_snapshot' => $calculation,
                    'formula_version' => $result['formula_version'],
                    'otr_price' => $inputs['otr_price'],
                    'cash_down_payment' => $inputs['cash_down_payment'],
                    'trade_in_value' => $inputs['trade_in_value'],
                    'old_vehicle_payoff' => $inputs['old_vehicle_payoff'],
                    'trade_in_equity' => $calculation['trade_in_equity'],
                    'use_trade_in_as_dp' => $inputs['use_trade_in_as_dp'],
                    'approved_discount' => $calculation['approved_discount'],
                    'total_down_payment' => $calculation['total_down_payment'],
                    'principal' => $calculation['principal'],
                    'tenor_months' => $inputs['tenor_months'],
                    'annual_flat_rate_basis_points' => $calculation[
                        'annual_flat_rate_basis_points'
                    ],
                    'total_flat_interest' => $calculation[
                        'total_flat_interest'
                    ],
                    'monthly_installment' => $calculation[
                        'monthly_installment'
                    ],
                    'administration_fee' => $calculation[
                        'administration_fee'
                    ],
                    'provision_fee' => $calculation['provision_fee'],
                    'upfront_insurance' => $calculation['upfront_insurance'],
                    'other_upfront_costs' => $calculation[
                        'other_upfront_costs'
                    ],
                    'initial_payment' => $calculation['initial_payment'],
                    'total_payment' => $calculation['total_payment'],
                    'valid_until' => $result['valid_until'],
                    'campaign_source' => $data['campaign_source'] ?? null,
                    'idempotency_key' => $data['idempotency_key'],
                    'request_fingerprint' => $fingerprint,
                    'saved_at' => now(),
                ]);
            }, 3);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23505') {
                $replay = CreditSimulation::query()
                    ->where('user_id', $user->getKey())
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->first();
                if ($replay !== null
                    && hash_equals($replay->request_fingerprint, $fingerprint)) {
                    return [
                        'simulation' => $this->load($replay),
                        'replayed' => true,
                    ];
                }
            }

            throw $exception;
        }

        return [
            'simulation' => $this->load($simulation),
            'replayed' => false,
        ];
    }

    public function load(CreditSimulation $simulation): CreditSimulation
    {
        return $simulation->load([
            'program',
            'appraisal.latestResult',
            'followUpLead.assignedSales',
        ]);
    }

    /** @param array<string, mixed> $data */
    private function fingerprint(array $data): string
    {
        $canonical = $data;
        unset($canonical['idempotency_key']);
        $this->sortRecursively($canonical);

        return hash(
            'sha256',
            (string) json_encode(
                $canonical,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ),
        );
    }

    /** @param array<string, mixed> $value */
    private function sortRecursively(array &$value): void
    {
        ksort($value);
        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->sortRecursively($item);
            }
        }
    }

    private function referenceNumber(): string
    {
        return 'SK-'.now('Asia/Jakarta')->format('ymd').'-'
            .strtoupper(substr((string) Str::ulid(), -8));
    }
}
