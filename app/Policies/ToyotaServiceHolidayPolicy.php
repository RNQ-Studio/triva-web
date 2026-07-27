<?php

namespace App\Policies;

use App\Models\ToyotaServiceHoliday;
use App\Models\User;

class ToyotaServiceHolidayPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('toyota_service_config.viewAny');
    }

    public function view(User $user, ToyotaServiceHoliday $holiday): bool
    {
        return $user->can('toyota_service_config.view');
    }

    public function create(User $user): bool
    {
        return $user->can('toyota_service_config.create');
    }

    public function update(User $user, ToyotaServiceHoliday $holiday): bool
    {
        return $user->can('toyota_service_config.update');
    }

    public function delete(User $user, ToyotaServiceHoliday $holiday): bool
    {
        return $user->can('toyota_service_config.delete');
    }
}
