<?php

namespace App\Policies;

use App\Models\PartnerLogo;
use App\Models\User;

/**
 * Konten pemasaran beranda; izinnya mengikuti kelompok konten yang sama
 * dengan artikel, promo, dan banner beranda.
 */
class PartnerLogoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('articles.viewAny');
    }

    public function view(User $user, PartnerLogo $partnerLogo): bool
    {
        return $user->can('articles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('articles.create');
    }

    public function update(User $user, PartnerLogo $partnerLogo): bool
    {
        return $user->can('articles.update');
    }

    public function delete(User $user, PartnerLogo $partnerLogo): bool
    {
        return $user->can('articles.delete');
    }
}
