<?php

namespace App\Policies;

use App\Models\CreditProgram;
use App\Models\User;

class CreditProgramPolicy
{
    public function viewCatalog(User $user): bool
    {
        return true;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('credit_programs.viewAny');
    }

    public function view(User $user, CreditProgram $program): bool
    {
        return $user->can('credit_programs.view');
    }

    public function create(User $user): bool
    {
        return $user->can('credit_programs.create');
    }

    public function update(User $user, CreditProgram $program): bool
    {
        return $user->can('credit_programs.update');
    }

    public function delete(User $user, CreditProgram $program): bool
    {
        return false;
    }
}
