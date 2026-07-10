<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class OrderViewAuthorization
{
    public static function userCanViewOrder(?User $user, Order $order): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! RoleAccess::canAccessVisibilityArea($user, 'orders')) {
            return false;
        }

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'orders');

        if ($scope === 'all') {
            return true;
        }

        return self::userOwnsOrderRecord($order, (int) $user->id);
    }

    public static function userOwnsOrderRecord(Order $order, int $userId): bool
    {
        if ((int) $order->manager_id === $userId) {
            return true;
        }

        if (Schema::hasColumn('orders', 'order_owner_id') && (int) ($order->order_owner_id ?? 0) === $userId) {
            return true;
        }

        if (Schema::hasColumn('orders', 'dispatcher_id') && (int) ($order->dispatcher_id ?? 0) === $userId) {
            return true;
        }

        return false;
    }
}
