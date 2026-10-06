<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PartnerLogo;
use Illuminate\Http\Request;

/** @mixin PartnerLogo */
class AdminPartnerLogoResource extends PartnerLogoResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
