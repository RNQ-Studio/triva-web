<?php

namespace App\Policies;

use App\Models\ToyotaThsCoverage;
use App\Models\User;

class ToyotaThsCoveragePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('toyota_service_config.viewAny');
    }

    public function view(User $user, ToyotaThsCoverage $coverage): bool
    {
        return $user->can('toyota_service_config.view');
    }

    public function create(User $user): bool
    {
        return $user->can('toyota_service_config.create');
    }

    public function update(User $user, ToyotaThsCoverage $coverage): bool
    {
        return $user->can('toyota_service_config.update');
    }

    public function delete(User $user, ToyotaThsCoverage $coverage): bool
    {
        return $user->can('toyota_service_config.delete');
    }
}
