<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class TaskViewAuthorization
{
    /**
     * @param  Builder<Task>  $query
     */
    public static function applyTasksVisibilityScope(Builder $query, User $user): void
    {
        if ($user->isAdmin() || $user->isSupervisor()) {
            return;
        }

        if (! RoleAccess::canAccessVisibilityArea($user, 'tasks')) {
            $query->whereRaw('1 = 0');

            return;
        }

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'tasks');

        if ($scope === 'all') {
            return;
        }

        if ($scope === 'department') {
            $userIds = UserDashboardDepartmentScope::departmentUserIds($user);

            if ($userIds === []) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->whereIn('responsible_id', $userIds);

            return;
        }

        $query->where('responsible_id', $user->id);
    }
}
