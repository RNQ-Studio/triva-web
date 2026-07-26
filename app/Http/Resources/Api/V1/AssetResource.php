<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Asset
 */
class AssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'public_url' => $this->is_protected ? null : $this->getPublicUrl(),
            'temporary_url' => $this->is_protected ? $this->getTemporaryUrl() : null,
            'status' => $this->status->value,
            'retain_until' => $this->retain_until?->toIso8601String(),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
