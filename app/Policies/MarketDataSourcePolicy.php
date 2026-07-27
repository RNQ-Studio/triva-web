<?php

namespace App\Policies;

use App\Models\MarketDataSource;
use App\Models\User;

class MarketDataSourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('appraisal_market_sources.viewAny');
    }

    public function view(User $user, MarketDataSource $source): bool
    {
        return $user->can('appraisal_market_sources.view');
    }

    public function update(User $user, MarketDataSource $source): bool
    {
        return $user->hasRole('super-admin')
            && $user->can('appraisal_market_sources.update');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, MarketDataSource $source): bool
    {
        return false;
    }
}
