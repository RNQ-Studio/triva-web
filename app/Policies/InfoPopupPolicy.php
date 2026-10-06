<?php

namespace App\Policies;

use App\Models\InfoPopup;
use App\Models\User;

/**
 * Konten pemasaran beranda; izinnya mengikuti kelompok konten yang sama
 * dengan artikel, promo, dan banner beranda.
 */
class InfoPopupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('articles.viewAny');
    }

    public function view(User $user, InfoPopup $infoPopup): bool
    {
        return $user->can('articles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('articles.create');
    }

    public function update(User $user, InfoPopup $infoPopup): bool
    {
        return $user->can('articles.update');
    }

    public function delete(User $user, InfoPopup $infoPopup): bool
    {
        return $user->can('articles.delete');
    }
}
