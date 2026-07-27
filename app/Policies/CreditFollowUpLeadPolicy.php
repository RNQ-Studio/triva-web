<?php

namespace App\Policies;

use App\Models\CreditFollowUpLead;
use App\Models\User;

class CreditFollowUpLeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('credit_leads.viewAny');
    }

    public function view(User $user, CreditFollowUpLead $lead): bool
    {
        return $user->can('credit_leads.view')
            && ($user->hasAnyRole(['super-admin', 'admin'])
                || $lead->assigned_sales_id === $user->getKey());
    }

    public function update(User $user, CreditFollowUpLead $lead): bool
    {
        return $user->can('credit_leads.update')
            && ($user->hasAnyRole(['super-admin', 'admin'])
                || $lead->assigned_sales_id === $user->getKey());
    }

    public function delete(User $user, CreditFollowUpLead $lead): bool
    {
        return false;
    }
}
