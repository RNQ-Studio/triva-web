<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OtoxpertService;
use App\Models\OtoxpertWorkshopServicePrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OtoxpertService */
class OtoxpertServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var OtoxpertWorkshopServicePrice|null $price */
        $price = $this->relationLoaded('prices')
            ? $this->prices->first()
            : null;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'lead_time_days' => (int) (
                data_get($this->resource, 'pivot.lead_time_days')
                ?? $this->default_lead_time_days
            ),
            'indicative_price' => $price === null ? null : [
                'type' => $price->price_type,
                'minimum_amount' => $price->minimum_amount,
                'maximum_amount' => $price->maximum_amount,
                'currency' => $price->currency,
                'included_items' => $price->included_items,
                'excluded_items' => $price->excluded_items,
                'disclaimer' => $price->disclaimer,
                'effective_from' => $price->effective_from->toDateString(),
                'effective_to' => $price->effective_to?->toDateString(),
                'source' => 'official_otoxpert_publication',
                'verified_at' => $price->verified_at->toIso8601String(),
            ],
        ];
    }
}
