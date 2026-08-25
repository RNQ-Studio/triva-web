<?php

namespace App\Policies;

use App\Models\ToyotaSscCampaign;
use App\Models\User;

/**
 * Kampanye SSC adalah data master operasional Toyota, jadi izinnya menumpang
 * kelompok yang sama dengan konfigurasi servis Toyota lainnya.
 */
class ToyotaSscCampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('toyota_service_config.viewAny');
    }

    public function view(User $user, ToyotaSscCampaign $campaign): bool
    {
        return $user->can('toyota_service_config.view');
    }

    public function create(User $user): bool
    {
        return $user->can('toyota_service_config.create');
    }

    public function update(User $user, ToyotaSscCampaign $campaign): bool
    {
        return $user->can('toyota_service_config.update');
    }

    public function delete(User $user, ToyotaSscCampaign $campaign): bool
    {
        return $user->can('toyota_service_config.delete');
    }
}
