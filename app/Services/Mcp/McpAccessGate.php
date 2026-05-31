<?php

namespace App\Services\Mcp;

use App\Models\Order;
use App\Models\User;
use App\Support\RoleAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;

class McpAccessGate
{
    public function resolveUser(?Request $request = null): User
    {
        $user = $request?->user() ?? Auth::user();

        if ($user instanceof User) {
            if (! $user->is_active) {
                throw new AuthenticationException('Учётная запись деактивирована.');
            }

            return $user;
        }

        $devUserId = config('mcp.dev_user_id');

        if ($devUserId !== null && $devUserId !== '') {
            $devUser = User::query()->whereKey((int) $devUserId)->first();

            if ($devUser instanceof User && $devUser->is_active) {
                Auth::setUser($devUser);

                return $devUser;
            }
        }

        throw new AuthenticationException('Требуется Bearer-токен Sanctum (Authorization: Bearer …) или MCP_DEV_USER_ID для локального stdio.');
    }

    public function requireOrdersArea(User $user): void
    {
        if (! RoleAccess::canAccessVisibilityArea($user, 'orders')) {
            throw new AuthenticationException('Нет доступа к разделу «Заказы».');
        }
    }

    /**
     * @param  Builder<Order>  $query
     */
    public function applyOrdersScope(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'orders');

        if ($scope !== 'all') {
            $query->where('manager_id', $user->id);
        }
    }

    public function canViewFinance(User $user): bool
    {
        return $user->isAdmin()
            || RoleAccess::canAccessVisibilityArea($user, 'finance_salary')
            || RoleAccess::canAccessVisibilityArea($user, 'payment_schedules');
    }
}
