<?php

namespace App\Policies;

use App\Models\BodyPaintEstimate;
use App\Models\User;

class BodyPaintEstimatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function manageAny(User $user): bool
    {
        return $user->can('bp_estimates.viewAny');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, BodyPaintEstimate $estimate): bool
    {
        if ($estimate->user_id === $user->getKey()) {
            return true;
        }

        return $user->can('bp_estimates.view')
            && (
                $user->hasAnyRole(['super-admin', 'admin'])
                || $estimate->assigned_estimator_id === $user->getKey()
            );
    }

    public function updateCustomer(
        User $user,
        BodyPaintEstimate $estimate,
    ): bool {
        return $estimate->user_id === $user->getKey()
            && $estimate->status->isCustomerEditable();
    }

    public function submit(User $user, BodyPaintEstimate $estimate): bool
    {
        return $estimate->user_id === $user->getKey()
            && $estimate->status->isCustomerEditable();
    }

    public function decide(User $user, BodyPaintEstimate $estimate): bool
    {
        return $estimate->user_id === $user->getKey()
            && $estimate->status->exposesPublishedEstimate();
    }

    public function requestBooking(
        User $user,
        BodyPaintEstimate $estimate,
    ): bool {
        return $estimate->user_id === $user->getKey()
            && (
                in_array(
                    'request_booking',
                    $estimate->status->customerActions(),
                    true,
                )
                || $estimate->booking()->exists()
            );
    }

    public function manage(User $user, BodyPaintEstimate $estimate): bool
    {
        if (! $user->can('bp_estimates.update')) {
            return false;
        }

        return $user->hasAnyRole(['super-admin', 'admin'])
            || $estimate->assigned_estimator_id === $user->getKey();
    }
}
