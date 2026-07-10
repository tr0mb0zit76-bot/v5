<?php

namespace App\Services\Mcp;

use App\Models\Contractor;
use App\Models\Lead;
use App\Models\ManagementStatementImport;
use App\Models\ManagementStatementLine;
use App\Models\Order;
use App\Models\Task;
use App\Models\User;
use App\Support\LeadViewAuthorization;
use App\Support\McpTokenAbilities;
use App\Support\OrderPrintWorkflowLock;
use App\Support\OrderViewAuthorization;
use App\Support\RoleAccess;
use App\Support\TaskViewAuthorization;
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
        OrderViewAuthorization::applyOrdersVisibilityScope($query, $user, 'orders');
    }

    public function canViewFinance(User $user): bool
    {
        return $user->isAdmin()
            || RoleAccess::canAccessVisibilityArea($user, 'finance_salary')
            || RoleAccess::canAccessVisibilityArea($user, 'payment_schedules');
    }

    public function canAccessManagementAccounting(User $user): bool
    {
        return RoleAccess::canAccessManagementAccounting($user);
    }

    public function requireManagementAccounting(User $user): void
    {
        if (! $this->canAccessManagementAccounting($user)) {
            throw new AuthenticationException('Нет доступа к модулю «Управленческий учёт».');
        }
    }

    public function findAccessibleImport(User $user, int $importId): ManagementStatementImport
    {
        $this->requireManagementAccounting($user);

        $import = ManagementStatementImport::query()->whereKey($importId)->first();

        if ($import === null) {
            throw new AuthenticationException('Импорт выписки не найден.');
        }

        if (! $user->isAdmin() && (int) $import->imported_by !== (int) $user->id) {
            throw new AuthenticationException('Импорт доступен только загрузившему или администратору.');
        }

        return $import;
    }

    public function findAccessibleLine(User $user, int $lineId): ManagementStatementLine
    {
        $this->requireManagementAccounting($user);

        $line = ManagementStatementLine::query()
            ->with([
                'suggestedOrder:id,order_number',
                'suggestedPaymentSchedule:id,party,amount',
                'suggestedCategory:id,name,code',
                'suggestedUser:id,name',
            ])
            ->whereKey($lineId)
            ->first();

        if ($line === null) {
            throw new AuthenticationException('Строка выписки не найдена.');
        }

        if ($line->import_id !== null) {
            $import = ManagementStatementImport::query()->whereKey((int) $line->import_id)->first();

            if ($import === null) {
                throw new AuthenticationException('Импорт выписки не найден.');
            }

            if (! $user->isAdmin() && (int) $import->imported_by !== (int) $user->id) {
                throw new AuthenticationException('Импорт доступен только загрузившему или администратору.');
            }
        }

        return $line;
    }

    public function requireContractorsArea(User $user): void
    {
        if (! RoleAccess::canAccessVisibilityArea($user, 'contractors')) {
            throw new AuthenticationException('Нет доступа к разделу «Контрагенты».');
        }
    }

    public function requireDriversArea(User $user): void
    {
        if (! RoleAccess::canAccessVisibilityArea($user, 'drivers')) {
            throw new AuthenticationException('Нет доступа к разделу «Водители».');
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

        if (! TaskViewAuthorization::userCanAssignToUser($user, $responsibleId)) {
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

        if (! OrderViewAuthorization::userOwnsOrderRecord($order, (int) $user->id)) {
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

    public function requireMailArea(User $user): void
    {
        if (! RoleAccess::canAccessVisibilityArea($user, 'mail')) {
            throw new AuthenticationException('Нет доступа к разделу «Почта».');
        }
    }

    public function requireLeadsArea(User $user): void
    {
        if (! RoleAccess::canAccessVisibilityArea($user, 'leads')) {
            throw new AuthenticationException('Нет доступа к разделу «Лиды».');
        }
    }

    public function findAccessibleLead(User $user, int $leadId): Lead
    {
        $this->requireLeadsArea($user);

        /** @var Lead|null $lead */
        $lead = Lead::query()->whereKey($leadId)->first();

        if ($lead === null) {
            throw new AuthenticationException('Лид не найден.');
        }

        if ($user->isAdmin()) {
            return $lead;
        }

        if (! LeadViewAuthorization::userCanViewLead($user, $lead)) {
            throw new AuthenticationException('Лид недоступен.');
        }

        return $lead;
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function applyTasksScope(Builder $query, User $user): void
    {
        TaskViewAuthorization::applyTasksVisibilityScope($query, $user);
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

        return OrderViewAuthorization::userCanViewOrderForArea($user, $order, 'documents');
    }

    public function enforceTokenAbilityForTool(User $user, string $toolName): void
    {
        $this->requireTokenAbility($user, McpTokenAbilities::requiredAbilityForTool($toolName));
    }

    public function requireTokenAbility(User $user, string $ability): void
    {
        $token = $user->currentAccessToken();

        if ($token === null) {
            return;
        }

        if ($token->can(McpTokenAbilities::FULL) || $token->can($ability)) {
            return;
        }

        throw new AuthenticationException(
            $ability === McpTokenAbilities::WRITE
                ? 'Токен MCP не имеет права записи (mcp:write). Перевыпустите: php artisan mcp:issue-token {user} --write'
                : 'Недостаточно прав токена MCP для этого инструмента.',
        );
    }
}
