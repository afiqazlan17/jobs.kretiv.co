<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;
use App\Policies\Concerns\ScopesByDepartment;

/**
 * New — leads.kretiv.co has no Supabase precedent, so this mirrors
 * JobPolicy's shape: leads are day-to-day records any department member
 * manages, scoped by department visibility, with delete BOD-only for
 * consistency with every other table.
 */
class LeadPolicy
{
    use ScopesByDepartment;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lead $lead): bool
    {
        return $this->userCanAccessDepartment($user, $lead->department);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->userCanAccessDepartment($user, $lead->department);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->isBod();
    }
}
