<?php

namespace App\Http\Resources\Api\V1;

use App\Models\HomeBanner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HomeBanner */
class HomeBannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'image_url' => $this->imageUrl(),
            'link_url' => $this->link_url,
            'sort_order' => $this->sort_order,
        ];
    }
}
