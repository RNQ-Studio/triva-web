<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CreditSimulation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CreditSimulation */
class AdminCreditSimulationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $customer = (new CreditSimulationResource($this->resource))
            ->toArray($request);

        return [
            ...$customer,
            'customer' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user->getKey(),
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'city' => $this->user->city,
            ]),
            'appraisal' => $this->whenLoaded(
                'appraisal',
                fn (): ?array => $this->appraisal === null ? null : [
                    'id' => $this->appraisal->getKey(),
                    'reference_no' => $this->appraisal->reference_no,
                ],
            ),
            'totals' => [
                'otr_price' => $this->otr_price,
                'total_down_payment' => $this->total_down_payment,
                'principal' => $this->principal,
                'tenor_months' => $this->tenor_months,
                'monthly_installment' => $this->monthly_installment,
                'initial_payment' => $this->initial_payment,
                'total_payment' => $this->total_payment,
                'trade_in_value' => $this->trade_in_value,
                'currency' => 'IDR',
            ],
        ];
    }
}
