<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Appraisal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

abstract class CreditSimulationInputRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'cash_down_payment' => $this->input('cash_down_payment', 0),
            'manual_trade_in_value' => $this->input(
                'manual_trade_in_value',
                0,
            ),
            'old_vehicle_payoff' => $this->input('old_vehicle_payoff', 0),
            'use_trade_in_as_dp' => $this->boolean('use_trade_in_as_dp'),
            'accept_expired_appraisal' => $this->boolean(
                'accept_expired_appraisal',
            ),
        ]);
    }

    public function authorize(): bool
    {
        $userId = $this->user()?->getKey();
        if ($userId === null) {
            return false;
        }

        $appraisalId = $this->input('trade_in_appraisal_id');
        if (is_string($appraisalId) && Str::isUuid($appraisalId)) {
            $appraisal = Appraisal::query()->find($appraisalId);
            if ($appraisal !== null && $appraisal->user_id !== $userId) {
                return false;
            }
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'program_id' => [
                'required',
                'uuid',
                'exists:credit_programs,id',
            ],
            'otr_price' => [
                'required',
                'integer',
                'min:1',
                'max:999999999999',
            ],
            'cash_down_payment' => [
                'required',
                'integer',
                'min:0',
                'max:999999999999',
            ],
            'trade_in_appraisal_id' => [
                'nullable',
                'uuid',
                'exists:appraisals,id',
            ],
            'manual_trade_in_value' => [
                'required',
                'integer',
                'min:0',
                'max:999999999999',
            ],
            'use_trade_in_as_dp' => ['required', 'boolean'],
            'old_vehicle_payoff' => [
                'required',
                'integer',
                'min:0',
                'max:999999999999',
            ],
            'tenor_months' => ['required', 'integer', 'min:1', 'max:120'],
            'accept_expired_appraisal' => ['required', 'boolean'],
        ];
    }
}
