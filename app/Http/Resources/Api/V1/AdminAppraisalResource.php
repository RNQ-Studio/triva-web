<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Appraisal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Appraisal */
class AdminAppraisalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $customer = (new AppraisalResource($this->resource))->toArray($request);

        return [
            ...$customer,
            'customer' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user->getKey(),
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'city' => $this->user->city,
            ]),
            'assigned_appraiser' => $this->whenLoaded(
                'assignedAppraiser',
                fn (): ?array => $this->assignedAppraiser === null ? null : [
                    'id' => $this->assignedAppraiser->getKey(),
                    'name' => $this->assignedAppraiser->name,
                ],
            ),
            'expected_price_submitted_at' => $this->expected_price_submitted_at
                ?->toIso8601String(),
            'customer_decided_at' => $this->customer_decided_at
                ?->toIso8601String(),
            'inspection_notes' => $this->inspection_notes,
            'condition_percentage' => $this->condition_percentage,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
