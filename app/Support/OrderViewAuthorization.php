<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Schema;

final class OrderViewAuthorization
{
    public static function userCanViewOrder(?User $user, Order $order): bool
    {
        return self::userCanViewOrderForArea($user, $order, 'orders');
    }

    public static function userCanViewOrderForArea(?User $user, Order $order, string $area): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin() || $user->isSupervisor()) {
            return true;
        }

        if (! RoleAccess::canAccessVisibilityArea($user, $area)) {
            return false;
        }

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, $area);

        if ($scope === 'all') {
            return true;
        }

        if ($scope === 'department') {
            return self::orderRecordOwnedByAnyUser($order, UserDashboardDepartmentScope::departmentUserIds($user));
        }

        return self::userOwnsOrderRecord($order, (int) $user->id);
    }

    public static function userOwnsOrderRecord(Order $order, int $userId): bool
    {
        return self::orderRecordOwnedByAnyUser($order, [$userId]);
    }

    /**
     * @param  list<int>  $userIds
     */
    public static function orderRecordOwnedByAnyUser(Order $order, array $userIds): bool
    {
        $userIds = array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $userIds)));

        if ($userIds === []) {
            return false;
        }

        if (in_array((int) $order->manager_id, $userIds, true)) {
            return true;
        }

        if (Schema::hasColumn('orders', 'order_owner_id')
            && in_array((int) ($order->order_owner_id ?? 0), $userIds, true)) {
            return true;
        }

        if (Schema::hasColumn('orders', 'dispatcher_id')
            && in_array((int) ($order->dispatcher_id ?? 0), $userIds, true)) {
            return true;
        }

        return false;
    }

    /**
     * @param  Builder<Order>  $query
     */
    public static function applyOrdersVisibilityScope(Builder $query, User $user, string $area = 'orders'): void
    {
        if ($user->isAdmin() || $user->isSupervisor()) {
            return;
        }

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, $area);

        if ($scope === 'all') {
            return;
        }

        if ($scope === 'department') {
            self::applyUserIdsOwnsOrderScope($query, UserDashboardDepartmentScope::departmentUserIds($user));

            return;
        }

        self::applyUserOwnsOrderScope($query, (int) $user->id);
    }

    /**
     * @param  Builder<Order>  $query
     */
    public static function applyUserOwnsOrderScope(Builder $query, int $userId): void
    {
        self::applyUserIdsOwnsOrderScope($query, [$userId]);
    }

    /**
     * @param  Builder<Order>  $query
     */
    public static function applyUserIdsOwnsOrderScope(Builder $query, array $userIds): void
    {
        self::applyUserIdsOwnsOrderScopeToQuery(
            $query->getQuery(),
            $userIds,
            $query->getModel()->getTable(),
        );
    }

    /**
     * @param  list<int>  $userIds
     */
    public static function applyUserIdsOwnsOrderScopeToQuery(
        QueryBuilder $query,
        array $userIds,
        string $tablePrefix = 'orders',
    ): void {
        $userIds = array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $userIds)));

        if ($userIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $managerColumn = "{$tablePrefix}.manager_id";

        $query->where(function (QueryBuilder $ownedQuery) use ($userIds, $tablePrefix, $managerColumn): void {
            $ownedQuery->whereIn($managerColumn, $userIds);

            if (Schema::hasColumn('orders', 'order_owner_id')) {
                $ownedQuery->orWhereIn("{$tablePrefix}.order_owner_id", $userIds);
            }

            if (Schema::hasColumn('orders', 'dispatcher_id')) {
                $ownedQuery->orWhereIn("{$tablePrefix}.dispatcher_id", $userIds);
            }
        });
    }

    /**
     * Область видимости заказов для query builder (join payment_schedules → orders и т.п.).
     */
    public static function applyOrdersVisibilityScopeToQuery(
        QueryBuilder $query,
        User $user,
        string $area = 'orders',
        string $tablePrefix = 'orders',
    ): void {
        if ($user->isAdmin() || $user->isSupervisor()) {
            return;
        }

        if (! RoleAccess::canAccessVisibilityArea($user, $area)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, $area);

        if ($scope === 'all') {
            return;
        }

        if ($scope === 'department') {
            self::applyUserIdsOwnsOrderScopeToQuery(
                $query,
                UserDashboardDepartmentScope::departmentUserIds($user),
                $tablePrefix,
            );

            return;
        }

        self::applyUserIdsOwnsOrderScopeToQuery($query, [(int) $user->id], $tablePrefix);
    }
}
