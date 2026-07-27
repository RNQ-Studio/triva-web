<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ToyotaServiceBookingStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ToyotaServiceBookingStatusHistory */
class ToyotaServiceBookingTimelineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->customerLabel(),
            'event' => $this->event,
            'title' => $this->title,
            'description' => $this->description,
            'reason_code' => $this->reason_code,
            'actor_type' => $this->actor_type,
            'actor' => $this->when(
                $this->relationLoaded('changedBy') && $this->changedBy !== null,
                fn (): array => [
                    'id' => $this->changedBy->getKey(),
                    'name' => $this->changedBy->name,
                ],
            ),
            'metadata' => $this->when($this->metadata !== null, $this->metadata),
            'occurred_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
