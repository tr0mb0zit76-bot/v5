<?php

namespace App\Support;

final class OrderDeleteAuthorization
{
    /**
     * Эффективный статус заказа для прав доступа: ручной статус перекрывает системный.
     */
    public static function effectiveWorkflowStatus(?string $manualStatus, ?string $systemStatus): string
    {
        $manual = self::trimmed($manualStatus);
        if ($manual !== null) {
            return $manual;
        }

        $system = self::trimmed($systemStatus);

        return $system ?? 'new';
    }

    /**
     * Правила удаления: «Новый» — менеджер (свой) + руководитель + админ; «Выполняется» — руководитель + админ;
     * все прочие статусы — только администратор.
     */
    public static function userMayDelete(?string $roleName, ?int $userId, int $orderManagerId, ?string $manualStatus, ?string $systemStatus): bool
    {
        if ($userId === null || $roleName === null || $roleName === '') {
            return false;
        }

        if ($roleName === 'admin') {
            return true;
        }

        $effective = self::effectiveWorkflowStatus($manualStatus, $systemStatus);

        if ($effective === 'new') {
            if ($roleName === 'supervisor') {
                return true;
            }

            return $roleName === 'manager' && $orderManagerId === $userId;
        }

        if ($effective === 'in_progress') {
            return $roleName === 'supervisor';
        }

        return false;
    }

    private static function trimmed(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $t = trim($value);

        return $t === '' ? null : $t;
    }
}
