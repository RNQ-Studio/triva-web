<?php

namespace App\Policies;

use App\Models\Promotion;
use App\Models\User;

/**
 * Promo adalah konten pemasaran cabang, jadi izinnya mengikuti kelompok
 * konten yang sama dengan artikel.
 */
class PromotionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('articles.viewAny');
    }

    public function view(User $user, Promotion $promotion): bool
    {
        return $user->can('articles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('articles.create');
    }

    public function update(User $user, Promotion $promotion): bool
    {
        return $user->can('articles.update');
    }

    public function delete(User $user, Promotion $promotion): bool
    {
        return $user->can('articles.delete');
    }
}
