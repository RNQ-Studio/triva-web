<?php

namespace App\Policies;

use App\Models\ToyotaServiceType;
use App\Models\User;

class ToyotaServiceTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('toyota_service_config.viewAny');
    }

    public function view(User $user, ToyotaServiceType $serviceType): bool
    {
        return $user->can('toyota_service_config.view');
    }

    public function create(User $user): bool
    {
        return $user->can('toyota_service_config.create');
    }

    public function update(User $user, ToyotaServiceType $serviceType): bool
    {
        return $user->can('toyota_service_config.update');
    }

    public function delete(User $user, ToyotaServiceType $serviceType): bool
    {
        return $user->can('toyota_service_config.delete');
    }
}
