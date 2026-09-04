<?php

namespace App\Policies;

use App\Models\SalesContact;
use App\Models\User;

/**
 * Data sales dikelola admin cabang; izinnya mengikuti kelompok pengelolaan
 * pengguna karena menyangkut kontak personal staf.
 */
class SalesContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.viewAny');
    }

    public function view(User $user, SalesContact $salesContact): bool
    {
        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, SalesContact $salesContact): bool
    {
        return $user->can('users.update');
    }

    public function delete(User $user, SalesContact $salesContact): bool
    {
        return $user->can('users.delete');
    }
}
