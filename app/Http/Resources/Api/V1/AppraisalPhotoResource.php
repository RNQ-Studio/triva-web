<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AppraisalPhoto;
use App\Support\Enums\AppraisalPhotoReviewStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AppraisalPhoto */
class AppraisalPhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'angle' => $this->angle->value,
            'angle_label' => $this->angle->label(),
            'version' => $this->version,
            'review_status' => $this->review_status->value,
            'rejection_note' => $this->when(
                $this->review_status === AppraisalPhotoReviewStatus::Rejected,
                $this->rejection_note,
            ),
            'url' => $this->asset?->getTemporaryUrl(),
            'uploaded_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
