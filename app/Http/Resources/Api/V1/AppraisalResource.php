<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Appraisal;
use App\Support\Enums\AppraisalDecision;
use App\Support\Enums\AppraisalStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Appraisal */
class AppraisalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resultVisible = in_array($this->status, [
            AppraisalStatus::ResultReady,
            AppraisalStatus::AcceptedByCustomer,
            AppraisalStatus::RejectedByCustomer,
            AppraisalStatus::InspectionScheduled,
            AppraisalStatus::Converted,
            AppraisalStatus::Expired,
        ], true);

        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'status' => $this->status->value,
            'status_label' => $this->status->customerLabel(),
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
            'condition' => [
                'tax_status' => $this->tax_status,
                'flood_history' => $this->flood_history,
                'major_accident_history' => $this->major_accident_history,
                'service_history' => $this->service_history,
                'ownership' => $this->ownership,
                'condition_percentage' => $this->condition_percentage,
                'engine_condition' => $this->engine_condition,
                'tyre_condition' => $this->tyre_condition,
            ],
            'photos' => AppraisalPhotoResource::collection($this->whenLoaded('currentPhotos')),
            'timeline' => AppraisalTimelineResource::collection($this->whenLoaded('statusHistories')),
            'result' => $this->when(
                $resultVisible && $this->relationLoaded('latestResult') && $this->latestResult !== null,
                fn () => new AppraisalResultResource($this->latestResult),
            ),
            'customer_decision' => $this->customer_decision?->value,
            'continuation' => $this->continuation(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'inspection_scheduled_at' => $this->inspection_scheduled_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function continuation(): ?array
    {
        $result = $this->latestResult;

        return match ($this->customer_decision) {
            AppraisalDecision::Accepted => [
                'type' => 'credit_simulation',
                'vehicle_id' => $this->vehicle_id,
                'appraisal_id' => $this->id,
                'suggested_trade_in_low' => $result?->trade_in_low,
                'suggested_trade_in_high' => $result?->trade_in_high,
            ],
            AppraisalDecision::Rejected => [
                'type' => 'body_paint_estimate',
                'vehicle_id' => $this->vehicle_id,
                'appraisal_id' => $this->id,
            ],
            default => null,
        };
    }
}
