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
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $avatarUrl = null;
        if ($this->avatar !== null) {
            if (Str::isUuid($this->avatar)) {
                $avatarUrl = $this->avatarAsset?->getPublicUrl();
            } elseif (Str::startsWith($this->avatar, 'https://')) {
                $avatarUrl = $this->avatar;
            } else {
                $avatarUrl = app(FileUploadService::class)->url($this->avatar);
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'city' => $this->city,
            'gender' => $this->gender?->value,
            'birth_date' => $this->birth_date?->toDateString(),
            'age' => $this->age(),
            'avatar_url' => $avatarUrl,
            'profile_completed' => filled($this->phone)
                && filled($this->city)
                && $this->service_consent_at !== null,
            // Dipisahkan dari `profile_completed` supaya pemasangan lama —
            // yang tidak mengenal isian ini — tidak terkunci di layar
            // lengkapi profil tanpa cara memperbaiki diri.
            'demographics_completed' => $this->hasCompletedDemographics(),
            'service_consent_at' => $this->service_consent_at?->toIso8601String(),
            'marketing_consent' => $this->marketing_consent,
            'is_active' => $this->is_active,
            'roles' => $this->getRoleNames()->values(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values(),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'phone_verified_at' => $this->phone_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
