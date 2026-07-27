<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ToyotaServiceBookingPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ToyotaServiceBookingPhoto */
class ToyotaServiceBookingPhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset' => new AssetResource($this->whenLoaded('asset')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
