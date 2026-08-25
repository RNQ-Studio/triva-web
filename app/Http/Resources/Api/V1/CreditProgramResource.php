<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CreditProgram;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CreditProgram */
class CreditProgramResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'program_code' => $this->program_code,
            'version' => $this->version,
            'partner_name' => $this->partner_name,
            'program_name' => $this->program_name,
            'city' => $this->city,
            'vehicle' => [
                'model' => $this->vehicle_model,
                'variant' => $this->vehicle_variant,
                'model_year' => $this->model_year,
                'otr_price' => $this->otr_price,
                'approved_discount' => $this->approved_discount,
            ],
            'package_code' => $this->package_code,
            'recommended_dp_basis_points' => $this->recommended_dp_basis_points,
            // DP anjuran paket -- SPEKTA memakai 20% -- supaya aplikasi bisa
            // langsung mengisikan nominalnya alih-alih memakai DP minimum.
            'recommended_dp_amount' => $this->recommended_dp_basis_points === null
                ? null
                : $this->roundedUpPercentage($this->recommended_dp_basis_points),
            'minimum_dp_basis_points' => $this->minimum_dp_basis_points,
            'maximum_dp_basis_points' => $this->maximum_dp_basis_points,
            'minimum_dp_amount' => $this->roundedUpPercentage(
                $this->minimum_dp_basis_points,
            ),
            'maximum_dp_amount' => $this->roundedDownPercentage(
                $this->maximum_dp_basis_points,
            ),
            'tenor_options' => collect($this->tenor_options)
                ->sortBy('tenor_months')
                ->values()
                ->all(),
            'formula_strategy' => $this->formula_strategy,
            'formula_version' => $this->formula_version,
            'effective_from' => $this->effective_from->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'source_reference' => $this->source_reference,
            'is_demo' => $this->is_demo,
            'is_estimate' => true,
            'disclaimer' => 'Hasil simulasi bersifat estimasi dan bukan persetujuan kredit.',
        ];
    }

    private function roundedUpPercentage(int $basisPoints): int
    {
        return intdiv(
            ($this->otr_price * $basisPoints) + 9999,
            10000,
        );
    }

    private function roundedDownPercentage(int $basisPoints): int
    {
        return intdiv($this->otr_price * $basisPoints, 10000);
    }
}
