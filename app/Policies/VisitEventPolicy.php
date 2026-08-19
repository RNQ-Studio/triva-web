<?php

namespace App\Policies;

use App\Models\User;

class VisitEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('analytics.viewAny');
    }
}
