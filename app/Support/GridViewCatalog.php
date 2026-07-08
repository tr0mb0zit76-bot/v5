<?php

namespace App\Support;

use App\Models\User;

final class GridViewCatalog
{
    /**
     * @return list<string>
     */
    public static function gridKeys(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @return array<string, array{label: string, route: string, visibility_area: string|null}>
     */
    public static function definitions(): array
    {
        return [
            'orders' => [
                'label' => 'Заказы',
                'route' => 'orders.index',
                'visibility_area' => 'orders',
            ],
            'disposition' => [
                'label' => 'Диспозиция',
                'route' => 'disposition.index',
                'visibility_area' => 'orders',
            ],
            'documents' => [
                'label' => 'Документы',
                'route' => 'documents.index',
                'visibility_area' => 'documents',
            ],
            'leads' => [
                'label' => 'Лиды',
                'route' => 'leads.index',
                'visibility_area' => 'leads',
            ],
            'contractors' => [
                'label' => 'Контрагенты',
                'route' => 'contractors.index',
                'visibility_area' => 'contractors',
            ],
            'payment_schedule' => [
                'label' => 'График оплат',
                'route' => 'finance.index',
                'visibility_area' => 'payment_schedules',
            ],
            'load_board' => [
                'label' => 'Биржа грузов',
                'route' => 'load-board.index',
                'visibility_area' => 'load_board',
            ],
            'company_planning' => [
                'label' => 'План компании',
                'route' => 'company-planning.index',
                'visibility_area' => 'company_planning',
            ],
        ];
    }

    public static function isValidGridKey(string $gridKey): bool
    {
        return array_key_exists($gridKey, self::definitions());
    }

    public static function labelFor(string $gridKey): string
    {
        return self::definitions()[$gridKey]['label'] ?? $gridKey;
    }

    public static function routeNameFor(string $gridKey): ?string
    {
        return self::definitions()[$gridKey]['route'] ?? null;
    }

    public static function urlForView(string $gridKey, int $viewId): ?string
    {
        $routeName = self::routeNameFor($gridKey);

        if ($routeName === null) {
            return null;
        }

        $base = route($routeName, absolute: false);

        if ($gridKey === 'payment_schedule') {
            $separator = str_contains($base, '?') ? '&' : '?';

            return $base.$separator.'section=cashflow&view='.$viewId;
        }

        $separator = str_contains($base, '?') ? '&' : '?';

        return $base.$separator.'view='.$viewId;
    }

    public static function userCanAccessGrid(?User $user, string $gridKey): bool
    {
        if ($user === null || ! self::isValidGridKey($gridKey)) {
            return false;
        }

        if (RoleAccess::isAdminUser($user)) {
            return true;
        }

        if ($gridKey === 'company_planning') {
            return RoleAccess::canAccessCompanyPlanning($user);
        }

        $area = self::definitions()[$gridKey]['visibility_area'] ?? null;

        if ($area === null) {
            return true;
        }

        return RoleAccess::canAccessVisibilityArea($user, $area);
    }

    /**
     * @return list<string>
     */
    public static function visibilityOptions(): array
    {
        return ['private', 'role', 'users', 'workspace'];
    }
}
