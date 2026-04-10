<?php

namespace App\Policies;

use App\Models\SalesScriptTransition;
use App\Models\User;
use App\Support\RoleAccess;

class SalesScriptTransitionPolicy
{
    public function update(User $user, SalesScriptTransition $transition): bool
    {
        return RoleAccess::canManageSalesScripts($user);
    }

    public function delete(User $user, SalesScriptTransition $transition): bool
    {
        return RoleAccess::canManageSalesScripts($user);
    }
}
