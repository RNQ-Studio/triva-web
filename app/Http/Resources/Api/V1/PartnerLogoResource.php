<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PartnerLogo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PartnerLogo */
class PartnerLogoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'logo_url' => $this->logoUrl(),
            'link_url' => $this->link_url,
            'sort_order' => $this->sort_order,
        ];
    }
}
