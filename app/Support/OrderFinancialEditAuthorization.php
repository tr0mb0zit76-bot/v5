<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;

/**
 * Редактирование ставок и финансовых условий заказа.
 * Менеджер не меняет финансы, когда рейс «Выполняется»; админ и руководитель — могут.
 */
final class OrderFinancialEditAuthorization
{
    public static function userMayEditFinancialFields(?User $user, Order $order): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin() || $user->isSupervisor()) {
            return true;
        }

        if (! $user->isManager()) {
            return false;
        }

        if ((int) $order->manager_id !== (int) $user->id) {
            return false;
        }

        $effective = OrderDeleteAuthorization::effectiveWorkflowStatus(
            $order->manual_status,
            $order->status,
        );

        return $effective !== 'in_progress';
    }

    public static function userMayEditFinancialFieldsForRow(
        ?User $user,
        ?string $roleName,
        int $userId,
        int $orderManagerId,
        ?string $manualStatus,
        ?string $systemStatus,
    ): bool {
        if ($roleName === 'admin' || $roleName === 'supervisor') {
            return true;
        }

        if ($user !== null && ($user->isAdmin() || $user->isSupervisor())) {
            return true;
        }

        if ($roleName !== 'manager') {
            return false;
        }

        if ($orderManagerId !== $userId) {
            return false;
        }

        $effective = OrderDeleteAuthorization::effectiveWorkflowStatus($manualStatus, $systemStatus);

        return $effective !== 'in_progress';
    }
}
