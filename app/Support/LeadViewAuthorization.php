<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class LeadViewAuthorization
{
    public static function userCanViewLead(?User $user, Lead $lead): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin() || $user->isSupervisor()) {
            return true;
        }

        if (! RoleAccess::canAccessVisibilityArea($user, 'leads')) {
            return false;
        }

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'leads');

        if ($scope === 'all') {
            return true;
        }

        if ($lead->responsible_id === null) {
            return false;
        }

        if ($scope === 'department') {
            return in_array(
                (int) $lead->responsible_id,
                UserDashboardDepartmentScope::departmentUserIds($user),
                true,
            );
        }

        return (int) $lead->responsible_id === (int) $user->id;
    }

    /**
     * @param  Builder<Lead>  $query
     */
    public static function applyLeadsVisibilityScope(Builder $query, User $user, bool $includeUnassigned = false): void
    {
        if ($user->isAdmin() || $user->isSupervisor()) {
            return;
        }

        if (! RoleAccess::canAccessVisibilityArea($user, 'leads')) {
            $query->whereRaw('1 = 0');

            return;
        }

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'leads');

        if ($scope === 'all') {
            return;
        }

        if ($scope === 'department') {
            self::applyResponsibleIdsScope($query, UserDashboardDepartmentScope::departmentUserIds($user), $includeUnassigned);

            return;
        }

        if ($includeUnassigned) {
            $query->where(function (Builder $ownedQuery) use ($user): void {
                $ownedQuery->where('responsible_id', $user->id)
                    ->orWhereNull('responsible_id');
            });

            return;
        }

        $query->where('responsible_id', $user->id);
    }

    public static function applyLeadsVisibilityScopeToQuery(
        QueryBuilder $query,
        User $user,
        string $tablePrefix = 'leads',
        bool $includeUnassigned = false,
    ): void {
        if ($user->isAdmin() || $user->isSupervisor()) {
            return;
        }

        if (! RoleAccess::canAccessVisibilityArea($user, 'leads')) {
            $query->whereRaw('1 = 0');

            return;
        }

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'leads');

        if ($scope === 'all') {
            return;
        }

        $column = "{$tablePrefix}.responsible_id";

        if ($scope === 'department') {
            $userIds = UserDashboardDepartmentScope::departmentUserIds($user);

            if ($userIds === []) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where(function (QueryBuilder $ownedQuery) use ($column, $userIds, $includeUnassigned): void {
                $ownedQuery->whereIn($column, $userIds);

                if ($includeUnassigned) {
                    $ownedQuery->orWhereNull($column);
                }
            });

            return;
        }

        if ($includeUnassigned) {
            $query->where(function (QueryBuilder $ownedQuery) use ($column, $user): void {
                $ownedQuery->where($column, $user->id)
                    ->orWhereNull($column);
            });

            return;
        }

        $query->where($column, $user->id);
    }

    /**
     * @param  Builder<Lead>  $query
     * @param  list<int>  $userIds
     */
    private static function applyResponsibleIdsScope(Builder $query, array $userIds, bool $includeUnassigned): void
    {
        if ($userIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $ownedQuery) use ($userIds, $includeUnassigned): void {
            $ownedQuery->whereIn('responsible_id', $userIds);

            if ($includeUnassigned) {
                $ownedQuery->orWhereNull('responsible_id');
            }
        });
    }
}
