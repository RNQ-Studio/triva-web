<?php

namespace App\Policies;

use App\Models\CreditSimulation;
use App\Models\User;

class CreditSimulationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, CreditSimulation $simulation): bool
    {
        if ($simulation->user_id === $user->getKey()) {
            return true;
        }
        if (! $user->can('credit_leads.view')) {
            return false;
        }

        return $user->hasAnyRole(['super-admin', 'admin'])
            || $simulation->followUpLead?->assigned_sales_id === $user->getKey();
    }

    public function requestFollowUp(
        User $user,
        CreditSimulation $simulation,
    ): bool {
        return $simulation->user_id === $user->getKey();
    }
}
