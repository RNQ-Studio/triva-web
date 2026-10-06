<?php

namespace App\Http\Resources\Api\V1;

use App\Models\InfoPopup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InfoPopup */
class InfoPopupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'image_url' => $this->imageUrl(),
            'button_label' => $this->button_label,
            'button_url' => $this->button_url,
            'sort_order' => $this->sort_order,
            'interval_hours' => $this->interval_hours,
            // Aplikasi memakai stempel ini untuk menayangkan ulang popup yang
            // diubah admin sebelum jedanya habis.
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
