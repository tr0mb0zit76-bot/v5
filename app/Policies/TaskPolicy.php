<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Support\RoleAccess;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'tasks');
    }

    public function view(User $user, Task $task): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $areas = RoleAccess::userVisibilityAreas($user);

        if (! RoleAccess::hasVisibilityArea($areas, 'tasks')
            && ! RoleAccess::hasVisibilityArea($areas, 'kanban')) {
            return false;
        }

        $scope = RoleAccess::resolveVisibilityScope($user->role?->name, $user->role?->visibility_scopes, 'tasks');

        return $scope === 'all' || (int) $task->responsible_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'tasks');
    }

    public function update(User $user, Task $task): bool
    {
        return RoleAccess::canMutateTask($user, $task);
    }

    /**
     * Массовое переназначение ответственных.
     */
    public function bulkAssign(User $user): bool
    {
        return RoleAccess::canBulkMutateTasks($user);
    }
}
