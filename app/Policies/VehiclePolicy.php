<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $vehicle->user_id === $user->getKey()
            || $user->can('vehicles.view');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $vehicle->user_id === $user->getKey()
            || $user->can('vehicles.update');
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $vehicle->user_id === $user->getKey()
            || $user->can('vehicles.delete');
    }
}
