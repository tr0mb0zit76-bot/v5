<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class TaskViewAuthorization
{
    public static function userCanViewTask(?User $user, Task $task): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin() || $user->isSupervisor()) {
            return true;
        }

        if (! RoleAccess::canAccessVisibilityArea($user, 'tasks')) {
            return false;
        }

        if ($task->responsible_id === null) {
            return false;
        }

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'tasks');

        if ($scope === 'all') {
            return true;
        }

        if ($scope === 'department') {
            return in_array(
                (int) $task->responsible_id,
                UserDashboardDepartmentScope::departmentUserIds($user),
                true,
            );
        }

        return (int) $task->responsible_id === (int) $user->id;
    }

    public static function userCanAssignToUser(?User $user, int $responsibleId): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin() || $user->isSupervisor()) {
            return true;
        }

        if (! RoleAccess::canAccessVisibilityArea($user, 'tasks')) {
            return false;
        }

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'tasks');

        if ($scope === 'all') {
            return true;
        }

        if ($scope === 'department') {
            return in_array($responsibleId, UserDashboardDepartmentScope::departmentUserIds($user), true);
        }

        return $responsibleId === (int) $user->id;
    }

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
