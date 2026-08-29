<?php

namespace App\Policies;

use App\Models\Appraisal;
use App\Models\User;
use App\Support\Enums\AppraisalStatus;

class AppraisalPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Melihat seluruh appraisal pelanggan dari panel admin. */
    public function manageAny(User $user): bool
    {
        return $user->can('appraisals.viewAny');
    }

    public function view(User $user, Appraisal $appraisal): bool
    {
        return $appraisal->user_id === $user->getKey()
            || $user->can('appraisals.view');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Appraisal $appraisal): bool
    {
        return $appraisal->user_id === $user->getKey()
            && $appraisal->status->isCustomerEditable();
    }

    public function decide(User $user, Appraisal $appraisal): bool
    {
        return $appraisal->user_id === $user->getKey()
            && $appraisal->status === AppraisalStatus::ResultReady;
    }

    public function manageAutomaticProcessing(
        User $user,
        Appraisal $appraisal,
    ): bool {
        return $user->can('appraisals.update');
    }
}
