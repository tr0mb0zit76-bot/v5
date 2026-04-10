<?php

namespace App\Policies;

use App\Models\SalesScript;
use App\Models\User;
use App\Support\RoleAccess;

class SalesScriptPolicy
{
    public function viewAny(User $user): bool
    {
        return RoleAccess::canManageSalesScripts($user);
    }

    public function view(User $user, SalesScript $salesScript): bool
    {
        return RoleAccess::canManageSalesScripts($user);
    }

    public function create(User $user): bool
    {
        return RoleAccess::canManageSalesScripts($user);
    }

    public function update(User $user, SalesScript $salesScript): bool
    {
        return RoleAccess::canManageSalesScripts($user);
    }

    public function delete(User $user, SalesScript $salesScript): bool
    {
        return RoleAccess::canManageSalesScripts($user);
    }
}
