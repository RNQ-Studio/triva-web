<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Promotion */
class PromotionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'image_url' => $this->imageUrl(),
            'cta_label' => $this->cta_label,
            'cta_url' => $this->cta_url,
            'show_as_popup' => $this->show_as_popup,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
        ];
    }
}
