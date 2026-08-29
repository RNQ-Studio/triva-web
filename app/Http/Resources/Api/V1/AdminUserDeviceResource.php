<?php

namespace App\Http\Resources\Api\V1;

use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserDevice
 */
class AdminUserDeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'platform' => $this->platform->value,
            'device_name' => $this->device_name,
            'os_version' => $this->os_version,
            'app_version' => $this->app_version,
            'app_build' => $this->app_build,
            'last_active_at' => $this->last_active_at?->toIso8601String(),
        ];
    }
}
