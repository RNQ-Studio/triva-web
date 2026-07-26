<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AppraisalResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AppraisalResult */
class AppraisalResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'market_price' => [
                'low' => $this->market_low,
                'mid' => $this->market_mid,
                'high' => $this->market_high,
                'currency' => 'IDR',
            ],
            'trade_in_estimate' => [
                'low' => $this->trade_in_low,
                'high' => $this->trade_in_high,
                'currency' => 'IDR',
            ],
            'confidence' => $this->confidence->value,
            'comparable_count' => $this->comparable_count,
            'data_as_of' => $this->data_as_of->toIso8601String(),
            'valid_until' => $this->valid_until->toIso8601String(),
            'is_expired' => $this->valid_until->isPast(),
            'requires_physical_inspection' => $this->requires_physical_inspection,
            'disclaimer' => $this->disclaimer,
            'adjustments' => $this->adjustments ?? [],
            'published_at' => $this->published_at->toIso8601String(),
        ];
    }
}
