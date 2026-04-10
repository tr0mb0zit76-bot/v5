<?php

namespace App\Policies;

use App\Models\SalesScriptVersion;
use App\Models\User;
use App\Support\RoleAccess;

class SalesScriptVersionPolicy
{
    public function view(User $user, SalesScriptVersion $version): bool
    {
        return RoleAccess::canManageSalesScripts($user);
    }

    public function update(User $user, SalesScriptVersion $version): bool
    {
        return RoleAccess::canManageSalesScripts($user);
    }
}
