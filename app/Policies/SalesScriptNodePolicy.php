<?php

namespace App\Policies;

use App\Models\SalesScriptNode;
use App\Models\User;
use App\Support\RoleAccess;

class SalesScriptNodePolicy
{
    public function update(User $user, SalesScriptNode $node): bool
    {
        return RoleAccess::canManageSalesScripts($user);
    }

    public function delete(User $user, SalesScriptNode $node): bool
    {
        return RoleAccess::canManageSalesScripts($user);
    }
}
