<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleBenefitCheck;

class VehicleBenefitCheckPolicy
{
    public function view(User $user, VehicleBenefitCheck $benefitCheck): bool
    {
        return $benefitCheck->booking?->user_id === $user->getKey()
            || $user->can('service_bookings.view');
    }

    public function update(User $user, VehicleBenefitCheck $benefitCheck): bool
    {
        return $user->can('service_bookings.update');
    }
}
