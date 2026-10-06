<?php

namespace App\Http\Resources\Api\V1;

use App\Models\InfoPopup;
use Illuminate\Http\Request;

/** @mixin InfoPopup */
class AdminInfoPopupResource extends InfoPopupResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'is_active' => $this->is_active,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'is_running' => $this->isRunning(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
