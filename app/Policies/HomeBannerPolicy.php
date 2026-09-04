<?php

namespace App\Policies;

use App\Models\HomeBanner;
use App\Models\User;

/**
 * Banner beranda adalah konten pemasaran cabang; izinnya mengikuti kelompok
 * konten yang sama dengan artikel dan promo.
 */
class HomeBannerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('articles.viewAny');
    }

    public function view(User $user, HomeBanner $homeBanner): bool
    {
        return $user->can('articles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('articles.create');
    }

    public function update(User $user, HomeBanner $homeBanner): bool
    {
        return $user->can('articles.update');
    }

    public function delete(User $user, HomeBanner $homeBanner): bool
    {
        return $user->can('articles.delete');
    }
}
