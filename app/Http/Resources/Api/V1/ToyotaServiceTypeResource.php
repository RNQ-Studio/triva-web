<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ToyotaServiceType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ToyotaServiceType */
class ToyotaServiceTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fulfillmentTypes = [];
        if ($this->supports_workshop) {
            $fulfillmentTypes[] = 'workshop';
        }
        if ($this->supports_ths) {
            $fulfillmentTypes[] = 'ths';
        }

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'fulfillment_types' => $fulfillmentTypes,
            'workshop_lead_time_days' => $this->workshop_lead_time_days,
            'ths_lead_time_days' => $this->ths_lead_time_days,
            'sort_order' => $this->sort_order,
            'effective_from' => $this->effective_from->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
        ];
    }
}
