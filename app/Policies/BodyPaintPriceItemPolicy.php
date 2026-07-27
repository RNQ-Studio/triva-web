<?php

namespace App\Policies;

use App\Models\BodyPaintPriceItem;
use App\Models\User;

class BodyPaintPriceItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bp_price_matrix.viewAny');
    }

    public function view(User $user, BodyPaintPriceItem $item): bool
    {
        return $user->can('bp_price_matrix.view');
    }

    public function create(User $user): bool
    {
        return $user->can('bp_price_matrix.create');
    }

    public function update(User $user, BodyPaintPriceItem $item): bool
    {
        return $user->can('bp_price_matrix.update');
    }

    public function delete(User $user, BodyPaintPriceItem $item): bool
    {
        return false;
    }
}
