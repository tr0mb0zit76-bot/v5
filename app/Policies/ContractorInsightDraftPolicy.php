<?php

namespace App\Policies;

use App\Models\Contractor;
use App\Models\ContractorInsightDraft;
use App\Models\User;
use App\Support\RoleAccess;

class ContractorInsightDraftPolicy
{
    public function review(User $user, ContractorInsightDraft $draft): bool
    {
        if (! RoleAccess::canAccessVisibilityArea($user, 'contractors')) {
            return false;
        }

        return Contractor::query()
            ->visibleTo($user)
            ->whereKey($draft->contractor_id)
            ->exists();
    }
}
