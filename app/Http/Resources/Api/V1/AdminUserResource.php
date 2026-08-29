<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin User
 */
class AdminUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'city' => $this->city,
            'gender' => $this->gender?->value,
            'gender_label' => $this->gender?->label(),
            'birth_date' => $this->birth_date?->toDateString(),
            'age' => $this->age(),
            'avatar_url' => $this->avatarUrl(),
            'is_active' => $this->is_active,
            'is_admin' => $this->hasAnyRole(['admin', 'super-admin']),
            'roles' => $this->getRoleNames()->values(),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'phone_verified_at' => $this->phone_verified_at?->toIso8601String(),
            'service_consent_at' => $this->service_consent_at?->toIso8601String(),
            'marketing_consent' => $this->marketing_consent,
            'demographics_completed' => $this->hasCompletedDemographics(),
            'created_at' => $this->created_at?->toIso8601String(),
            'last_active_at' => $this->whenNotNull($this->lastActiveAt()),
            'activity' => $this->when(
                $this->hasActivityCounts(),
                fn (): array => [
                    'appraisals' => (int) ($this->appraisals_count ?? 0),
                    'toyota_service_bookings' => (int) ($this->toyota_service_bookings_count ?? 0),
                    'otoxpert_bookings' => (int) ($this->otoxpert_bookings_count ?? 0),
                    'credit_simulations' => (int) ($this->credit_simulations_count ?? 0),
                    'body_paint_estimates' => (int) ($this->body_paint_estimates_count ?? 0),
                    'vehicles' => (int) ($this->vehicles_count ?? 0),
                    'devices' => (int) ($this->devices_count ?? 0),
                ],
            ),
            'devices' => AdminUserDeviceResource::collection(
                $this->whenLoaded('devices'),
            ),
        ];
    }

    /** Diisi oleh `withMax('devices', 'last_active_at')`, bukan kolom tabel. */
    private function lastActiveAt(): ?string
    {
        $value = $this->resource->getAttribute('devices_max_last_active_at');

        return $value === null ? null : (string) $value;
    }

    private function hasActivityCounts(): bool
    {
        return $this->resource->getAttribute('appraisals_count') !== null;
    }

    private function avatarUrl(): ?string
    {
        if ($this->avatar === null) {
            return null;
        }

        if (Str::isUuid($this->avatar)) {
            return $this->avatarAsset?->getPublicUrl();
        }

        if (Str::startsWith($this->avatar, 'https://')) {
            return $this->avatar;
        }

        return app(FileUploadService::class)->url($this->avatar);
    }
}
