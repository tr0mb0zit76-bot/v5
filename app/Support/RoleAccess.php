<?php

namespace App\Support;

use App\Models\Task;
use App\Models\User;

class RoleAccess
{
    /**
     * @return list<array{key: string, label: string, description: string}>
     */
    public static function permissionOptions(): array
    {
        return [
            ['key' => 'manage_users', 'label' => 'Пользователи', 'description' => 'Создание, изменение и деактивация пользователей'],
            ['key' => 'manage_roles', 'label' => 'Роли', 'description' => 'Управление ролями, правами и областями видимости'],
            ['key' => 'view_reports', 'label' => 'Отчеты', 'description' => 'Доступ к аналитике и отчетам'],
            ['key' => 'view_orders', 'label' => 'Просмотр заказов', 'description' => 'Просмотр списка заказов'],
            ['key' => 'create_orders', 'label' => 'Создание заказов', 'description' => 'Создание новых заказов'],
            ['key' => 'edit_orders', 'label' => 'Редактирование заказов', 'description' => 'Изменение карточек заказов'],
            ['key' => 'assign_drivers', 'label' => 'Назначение водителей', 'description' => 'Привязка водителей и координация рейсов'],
            ['key' => 'view_finance', 'label' => 'Финансы', 'description' => 'Просмотр финансовых показателей'],
            ['key' => 'create_invoices', 'label' => 'Счета', 'description' => 'Создание счетов и финансовых документов'],
            ['key' => 'view_documents', 'label' => 'Документы', 'description' => 'Просмотр реестров документов'],
            ['key' => 'create_documents', 'label' => 'Создание документов', 'description' => 'Создание документов и шаблонов'],
            ['key' => 'edit_documents', 'label' => 'Редактирование документов', 'description' => 'Изменение документов'],
            ['key' => 'archive_documents', 'label' => 'Архив документов', 'description' => 'Архивирование и восстановление документов'],
            ['key' => 'manage_modules', 'label' => 'Модули', 'description' => 'Настройка доступных модулей'],
            ['key' => 'manage_settings', 'label' => 'Настройки', 'description' => 'Изменение системных настроек'],
        ];
    }

    /**
     * @return list<array{key: string, label: string, description: string}>
     */
    public static function visibilityAreaOptions(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Дашборд', 'description' => 'Главная панель и сводные карточки'],
            ['key' => 'dashboard_tiles', 'label' => 'Плитки дашборда', 'description' => 'Доступ к отдельным карточкам на дашборде'],
            ['key' => 'dashboard_widgets', 'label' => 'Виджеты дашборда', 'description' => 'Виджеты с трендами и дополнительными данными'],
            ['key' => 'dashboard_reports', 'label' => 'Отчёты в дашборде', 'description' => 'Расширенные отчёты и списки в дашборде'],
            ['key' => 'leads', 'label' => 'Лиды', 'description' => 'Воронка до конверсии в заказ'],
            ['key' => 'orders', 'label' => 'Заказы', 'description' => 'Раздел работы с заказами'],
            ['key' => 'users', 'label' => 'Пользователи', 'description' => 'Управление пользователями'],
            ['key' => 'roles', 'label' => 'Роли', 'description' => 'Управление ролями и правами'],
            ['key' => 'contractors', 'label' => 'Контрагенты', 'description' => 'Справочник контрагентов'],
            ['key' => 'drivers', 'label' => 'Водители', 'description' => 'Реестр водителей и перевозчиков'],
            ['key' => 'documents', 'label' => 'Документы', 'description' => 'Реестр документов'],
            ['key' => 'finance_salary', 'label' => 'Финансы: зарплата', 'description' => 'Зарплатные периоды, начисления и выплаты'],
            ['key' => 'tasks', 'label' => 'Задачи', 'description' => 'Управление внутренними и клиентскими задачами'],
            ['key' => 'kanban', 'label' => 'Канбан', 'description' => 'Визуальная доска задач'],
            ['key' => 'reports', 'label' => 'Отчеты', 'description' => 'Финансовые и операционные отчеты'],
            ['key' => 'modules', 'label' => 'Модули', 'description' => 'Каталог доступных модулей'],
            ['key' => 'scripts', 'label' => 'Помощник продаж', 'description' => 'Скрипты, база знаний и тренажёр; сценарии диалогов и материалы для менеджеров'],
            ['key' => 'settings', 'label' => 'Настройки (все подразделы)', 'description' => 'Полный доступ ко всем разделам настроек; для новых ролей предпочтительнее отдельные области ниже'],
            ['key' => 'settings_system', 'label' => 'Настройки: администрирование и конфигурация', 'description' => 'Пользователи, роли, таблицы, справочники и шаблоны печатных форм'],
            ['key' => 'settings_motivation', 'label' => 'Настройки: мотивация', 'description' => 'KPI и персональные условия (коэффициенты). Учёт зарплатных периодов — в модуле «Финансы»'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function visibilityScopeOptions(): array
    {
        return [
            ['value' => 'own', 'label' => 'Только своё'],
            ['value' => 'all', 'label' => 'Всё'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function permissionKeys(): array
    {
        return array_column(static::permissionOptions(), 'key');
    }

    /**
     * @return list<string>
     */
    public static function visibilityAreaKeys(): array
    {
        return array_column(static::visibilityAreaOptions(), 'key');
    }

    /**
     * @return list<string>
     */
    public static function defaultVisibilityAreas(?string $roleName): array
    {
        return match ($roleName) {
            'admin' => static::visibilityAreaKeys(),
            'supervisor' => ['dashboard', 'dashboard_tiles', 'dashboard_widgets', 'dashboard_reports', 'leads', 'orders', 'scripts', 'users', 'contractors', 'drivers', 'documents', 'finance_salary', 'tasks', 'kanban', 'reports', 'settings_motivation'],
            'manager' => ['dashboard', 'dashboard_tiles', 'dashboard_widgets', 'dashboard_reports', 'leads', 'orders', 'scripts', 'contractors', 'documents', 'tasks', 'kanban'],
            'dispatcher' => ['dashboard', 'dashboard_tiles', 'dashboard_widgets', 'dashboard_reports', 'orders', 'scripts', 'drivers', 'tasks', 'kanban'],
            'accountant' => ['dashboard', 'dashboard_tiles', 'dashboard_widgets', 'dashboard_reports', 'orders', 'documents', 'finance_salary', 'tasks', 'kanban', 'reports'],
            'clerk' => ['dashboard', 'dashboard_tiles', 'dashboard_widgets', 'dashboard_reports', 'orders', 'scripts', 'documents', 'contractors', 'tasks', 'kanban'],
            'viewer' => ['dashboard', 'dashboard_tiles', 'dashboard_widgets', 'dashboard_reports', 'orders'],
            default => ['dashboard'],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function defaultVisibilityScopes(?string $roleName): array
    {
        return match ($roleName) {
            'admin' => [
                'orders' => 'all',
                'leads' => 'all',
                'tasks' => 'all',
                'kanban' => 'all',
                'contractors' => 'all',
                'documents' => 'all',
                'dashboard_tiles' => 'all',
                'dashboard_widgets' => 'all',
                'dashboard_reports' => 'all',
            ],
            'supervisor' => [
                'orders' => 'all',
                'leads' => 'all',
                'tasks' => 'all',
                'kanban' => 'all',
                'contractors' => 'all',
                'documents' => 'all',
                'dashboard_tiles' => 'all',
                'dashboard_widgets' => 'all',
                'dashboard_reports' => 'all',
            ],
            'manager' => [
                'orders' => 'own',
                'leads' => 'own',
                'tasks' => 'own',
                'kanban' => 'own',
                'contractors' => 'own',
                'documents' => 'own',
                'dashboard_tiles' => 'own',
                'dashboard_widgets' => 'own',
                'dashboard_reports' => 'own',
            ],
            'dispatcher' => [
                'orders' => 'all',
                'tasks' => 'all',
                'kanban' => 'all',
                'dashboard_tiles' => 'all',
                'dashboard_widgets' => 'all',
                'dashboard_reports' => 'all',
            ],
            'accountant' => [
                'orders' => 'all',
                'tasks' => 'all',
                'kanban' => 'all',
                'documents' => 'all',
                'dashboard_tiles' => 'all',
                'dashboard_widgets' => 'all',
                'dashboard_reports' => 'all',
            ],
            'clerk' => [
                'orders' => 'all',
                'tasks' => 'all',
                'kanban' => 'all',
                'contractors' => 'all',
                'documents' => 'all',
                'dashboard_tiles' => 'all',
                'dashboard_widgets' => 'all',
                'dashboard_reports' => 'all',
            ],
            'viewer' => [
                'orders' => 'all',
                'dashboard_tiles' => 'all',
                'dashboard_widgets' => 'all',
                'dashboard_reports' => 'all',
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>|null  $visibilityScopes
     */
    public static function resolveVisibilityScope(?string $roleName, ?array $visibilityScopes, string $area): string
    {
        $scopes = is_array($visibilityScopes)
            ? $visibilityScopes
            : static::defaultVisibilityScopes($roleName);

        $value = $scopes[$area] ?? static::defaultVisibilityScopes($roleName)[$area] ?? 'own';

        return in_array($value, ['own', 'all'], true) ? $value : 'own';
    }

    /**
     * @return list<string>
     */
    public static function userVisibilityAreas(User $user): array
    {
        $role = $user->role;

        return is_array($role?->visibility_areas)
            ? $role->visibility_areas
            : static::defaultVisibilityAreas($role?->name);
    }

    /**
     * @param  list<string>  $areas
     */
    public static function hasVisibilityArea(array $areas, string $required): bool
    {
        if (in_array($required, $areas, true)) {
            return true;
        }

        if ($required === 'settings') {
            return in_array('settings_system', $areas, true)
                || in_array('settings_motivation', $areas, true);
        }

        if ($required === 'settings_system' || $required === 'settings_motivation') {
            $hasLegacyAllSettings = in_array('settings', $areas, true)
                && ! in_array('settings_system', $areas, true)
                && ! in_array('settings_motivation', $areas, true);

            if ($hasLegacyAllSettings) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $required
     */
    public static function hasAnyVisibilityArea(array $areas, array $required): bool
    {
        foreach ($required as $key) {
            if (static::hasVisibilityArea($areas, $key)) {
                return true;
            }
        }

        return false;
    }

    public static function canMutateTask(?User $user, Task $task): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! static::hasVisibilityArea(static::userVisibilityAreas($user), 'tasks')) {
            return false;
        }

        $scope = static::resolveVisibilityScope($user->role?->name, $user->role?->visibility_scopes, 'tasks');

        return $scope === 'all' || (int) $task->responsible_id === (int) $user->id;
    }

    /**
     * Массовые операции (переназначение чужих задач и т.п.) — только команда целиком или админ.
     */
    public static function canBulkMutateTasks(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! static::hasVisibilityArea(static::userVisibilityAreas($user), 'tasks')) {
            return false;
        }

        $scope = static::resolveVisibilityScope($user->role?->name, $user->role?->visibility_scopes, 'tasks');

        return $scope === 'all';
    }

    public static function canAccessSettingsSystem(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return static::hasVisibilityArea(static::userVisibilityAreas($user), 'settings_system');
    }

    /**
     * Редактор сценариев (структура версий, узлы, переходы) — только администраторы и роли с доступом к системным настройкам.
     */
    public static function canManageSalesScripts(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return static::canAccessSettingsSystem($user);
    }

    public static function canAccessSettingsMotivation(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return static::hasVisibilityArea(static::userVisibilityAreas($user), 'settings_motivation');
    }

    public static function canAccessSettingsOverview(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $areas = static::userVisibilityAreas($user);

        return static::hasVisibilityArea($areas, 'settings_system')
            || static::hasVisibilityArea($areas, 'settings_motivation');
    }

    public static function canAccessFinanceSalary(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return static::hasVisibilityArea(static::userVisibilityAreas($user), 'finance_salary');
    }
}
