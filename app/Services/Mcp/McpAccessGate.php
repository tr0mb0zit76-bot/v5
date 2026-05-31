<?php

namespace App\Services\Mcp;

use App\Models\Contractor;
use App\Models\Order;
use App\Models\Task;
use App\Models\User;
use App\Support\OrderPrintWorkflowLock;
use App\Support\RoleAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
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

    public function requireContractorsArea(User $user): void
    {
        if (! RoleAccess::canAccessVisibilityArea($user, 'contractors')) {
            throw new AuthenticationException('Нет доступа к разделу «Контрагенты».');
        }
    }

    public function requireTasksArea(User $user): void
    {
        if (! RoleAccess::canAccessVisibilityArea($user, 'tasks')) {
            throw new AuthenticationException('Нет доступа к разделу «Задачи».');
        }
    }

    public function ensureCanCreateTask(User $user, int $responsibleId): void
    {
        $this->requireTasksArea($user);

        if ($user->isAdmin()) {
            return;
        }

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'tasks');

        if ($scope !== 'all' && (int) $responsibleId !== (int) $user->id) {
            throw new AuthenticationException('Нельзя назначить задачу другому ответственному.');
        }
    }

    public function findAccessibleOrder(User $user, int $orderId): Order
    {
        $this->requireOrdersArea($user);

        $builder = Order::query()->whereKey($orderId);

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $builder->whereNull('deleted_at');
        }

        $this->applyOrdersScope($builder, $user);

        /** @var Order|null $order */
        $order = $builder->first();

        if ($order === null) {
            throw new AuthenticationException('Заказ не найден или недоступен.');
        }

        return $order;
    }

    public function ensureCanEditOrder(User $user, Order $order): void
    {
        $this->requireOrdersArea($user);

        if ($user->isAdmin() || $user->isSupervisor()) {
            return;
        }

        if (! $user->isManager()) {
            throw new AuthenticationException('Недостаточно прав для изменения заказа.');
        }

        if ((int) $order->manager_id !== (int) $user->id) {
            throw new AuthenticationException('Заказ недоступен для редактирования.');
        }

        if (OrderPrintWorkflowLock::allPrintWorkflowDocumentsFinalized($order)) {
            throw new AuthenticationException('Заказ заблокирован: все документы печатного workflow финализированы.');
        }
    }

    public function requireDocumentsArea(User $user): void
    {
        if (! RoleAccess::canAccessVisibilityArea($user, 'documents')) {
            throw new AuthenticationException('Нет доступа к разделу «Документы».');
        }
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function applyTasksScope(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'tasks');

        if ($scope !== 'all') {
            $query->where('responsible_id', $user->id);
        }
    }

    /**
     * @param  Builder<Contractor>  $query
     */
    public function applyContractorsScope(Builder $query, User $user): void
    {
        $query->visibleTo($user);
    }

    public function canAccessOrderDocuments(User $user, Order $order): bool
    {
        $this->requireDocumentsArea($user);

        if ($user->isAdmin() || $user->isSupervisor()) {
            return true;
        }

        $docScope = RoleAccess::resolveVisibilityScopeForUser($user, 'documents');

        if ($docScope === 'all') {
            return true;
        }

        return (int) $order->manager_id === (int) $user->id;
    }
}
