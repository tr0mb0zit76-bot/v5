<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Support\RoleAccess;
use App\Support\TaskViewAuthorization;

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

        return TaskViewAuthorization::userCanViewTask($user, $task);
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
