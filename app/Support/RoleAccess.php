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
            ['key' => 'manage_payment_schedules', 'label' => 'График оплат: действия', 'description' => 'Регистрация оплат, отмена/восстановление строк, правка номера счёта в графике'],
            ['key' => 'create_invoices', 'label' => 'Счета', 'description' => 'Создание счетов и финансовых документов'],
            ['key' => 'view_documents', 'label' => 'Документы', 'description' => 'Просмотр реестров документов'],
            ['key' => 'create_documents', 'label' => 'Создание документов', 'description' => 'Создание документов и шаблонов'],
            ['key' => 'edit_documents', 'label' => 'Редактирование документов', 'description' => 'Изменение документов'],
            ['key' => 'archive_documents', 'label' => 'Архив документов', 'description' => 'Архивирование и восстановление документов'],
            ['key' => 'manage_modules', 'label' => 'Модули', 'description' => 'Настройка доступных модулей'],
            ['key' => 'manage_settings', 'label' => 'Настройки', 'description' => 'Изменение системных настроек'],
            ['key' => 'sales_book_read', 'label' => 'Книга продаж: чтение', 'description' => 'Просмотр статей в книге продаж'],
            ['key' => 'sales_book_comment', 'label' => 'Книга продаж: комментарии', 'description' => 'Добавление комментариев в книге продаж'],
            ['key' => 'sales_book_write', 'label' => 'Книга продаж: редактирование', 'description' => 'Создание, редактирование и удаление статей в книге продаж'],
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
            ['key' => 'payment_schedules', 'label' => 'График оплат', 'description' => 'Плановые и фактические платежи по заказам (ДДС, график)'],
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
            'supervisor' => ['dashboard', 'dashboard_tiles', 'dashboard_widgets', 'dashboard_reports', 'leads', 'orders', 'scripts', 'users', 'contractors', 'drivers', 'documents', 'finance_salary', 'payment_schedules', 'tasks', 'kanban', 'reports', 'settings_motivation'],
            'manager' => ['dashboard', 'dashboard_tiles', 'dashboard_widgets', 'dashboard_reports', 'leads', 'orders', 'scripts', 'contractors', 'documents', 'payment_schedules', 'tasks', 'kanban'],
            'dispatcher' => ['dashboard', 'dashboard_tiles', 'dashboard_widgets', 'dashboard_reports', 'orders', 'scripts', 'drivers', 'payment_schedules', 'tasks', 'kanban'],
            'accountant' => ['dashboard', 'dashboard_tiles', 'dashboard_widgets', 'dashboard_reports', 'orders', 'documents', 'finance_salary', 'payment_schedules', 'tasks', 'kanban', 'reports'],
            'clerk' => ['dashboard', 'dashboard_tiles', 'dashboard_widgets', 'dashboard_reports', 'orders', 'scripts', 'documents', 'contractors', 'payment_schedules', 'tasks', 'kanban'],
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
                'payment_schedules' => 'all',
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
                'payment_schedules' => 'all',
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
                'payment_schedules' => 'own',
                'dashboard_tiles' => 'own',
                'dashboard_widgets' => 'own',
                'dashboard_reports' => 'own',
            ],
            'dispatcher' => [
                'orders' => 'all',
                'tasks' => 'all',
                'kanban' => 'all',
                'payment_schedules' => 'all',
                'dashboard_tiles' => 'all',
                'dashboard_widgets' => 'all',
                'dashboard_reports' => 'all',
            ],
            'accountant' => [
                'orders' => 'all',
                'tasks' => 'all',
                'kanban' => 'all',
                'documents' => 'all',
                'payment_schedules' => 'all',
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
                'payment_schedules' => 'all',
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
     * Нормализует visibility_scopes из БД/Eloquent (массив, JSON-строка, null).
     * Невалидная строка трактуется как отсутствие переопределений (null → дефолты роли).
     *
     * @return array<string, mixed>|null
     */
    public static function coerceVisibilityScopes(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : null;
        }

        if (is_array($raw)) {
            return $raw;
        }

        return null;
    }

    /**
     * Удобный вход, когда роль читается из DB::table / массива (не модель User).
     */
    public static function resolveVisibilityScopeForRolePayload(?string $roleName, mixed $rawVisibilityScopes, string $area): string
    {
        return static::resolveVisibilityScope($roleName, static::coerceVisibilityScopes($rawVisibilityScopes), $area);
    }

    /**
     * Разрешение области видимости для текущего пользователя (подгружает роль, нормализует scopes).
     */
    public static function resolveVisibilityScopeForUser(?User $user, string $area): string
    {
        if ($user === null) {
            return static::resolveVisibilityScope(null, null, $area);
        }

        $user->loadMissing('role');

        return static::resolveVisibilityScopeForRolePayload(
            $user->role?->name,
            $user->role?->visibility_scopes,
            $area
        );
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

        $scope = static::resolveVisibilityScopeForUser($user, 'tasks');

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

        $scope = static::resolveVisibilityScopeForUser($user, 'tasks');

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

    /**
     * Просмотр раздела «График оплат» (страница финансов / API чтения).
     */
    public static function canViewPaymentSchedules(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $areas = static::userVisibilityAreas($user);

        return static::hasVisibilityArea($areas, 'payment_schedules')
            || static::hasVisibilityArea($areas, 'documents')
            || static::hasVisibilityArea($areas, 'finance_salary');
    }

    /**
     * Объём строк графика оплат: при включённой области «График оплат» — её scope, иначе как у заказов.
     *
     * @return 'own'|'all'
     */
    public static function resolvePaymentScheduleDataScopeForUser(?User $user): string
    {
        if ($user === null) {
            return 'own';
        }

        $areas = static::userVisibilityAreas($user);
        if (static::hasVisibilityArea($areas, 'payment_schedules')) {
            return static::resolveVisibilityScopeForUser($user, 'payment_schedules');
        }

        return static::resolveVisibilityScopeForUser($user, 'orders');
    }

    /**
     * Действия в графике оплат (платежи, отмена, номер счёта и т.д.).
     * Явное право manage_payment_schedules обязательно, если в роли есть только область «График оплат».
     * Сочетание с «Финансы: зарплата» сохраняет прежнее поведение без отдельной галки права.
     */
    public static function canManagePaymentSchedules(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $areas = static::userVisibilityAreas($user);
        $hasPs = static::hasVisibilityArea($areas, 'payment_schedules');
        $hasFs = static::hasVisibilityArea($areas, 'finance_salary');

        if (! $hasPs && ! $hasFs) {
            return false;
        }

        if (static::userHasPermission($user, 'manage_payment_schedules')) {
            return true;
        }

        if ($hasPs && $hasFs) {
            return true;
        }

        return ! $hasPs && $hasFs;
    }

    /**
     * @return list<string>
     */
    public static function userPermissions(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $user->loadMissing('role');
        $raw = $user->role?->permissions;

        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter($raw, static fn (mixed $p): bool => is_string($p) && $p !== ''));
    }

    public static function userHasPermission(?User $user, string $permission): bool
    {
        return in_array($permission, static::userPermissions($user), true);
    }

    public static function canReadSalesBook(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! static::hasVisibilityArea(static::userVisibilityAreas($user), 'scripts')) {
            return false;
        }

        if (! static::hasAnySalesBookPermission($user)) {
            return true;
        }

        return static::userHasPermission($user, 'sales_book_read')
            || static::userHasPermission($user, 'sales_book_comment')
            || static::userHasPermission($user, 'sales_book_write');
    }

    public static function canCommentSalesBook(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! static::hasVisibilityArea(static::userVisibilityAreas($user), 'scripts')) {
            return false;
        }

        if (! static::hasAnySalesBookPermission($user)) {
            return true;
        }

        return static::userHasPermission($user, 'sales_book_comment')
            || static::userHasPermission($user, 'sales_book_write');
    }

    public static function canWriteSalesBook(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! static::hasVisibilityArea(static::userVisibilityAreas($user), 'scripts')) {
            return false;
        }

        if (! static::hasAnySalesBookPermission($user)) {
            return true;
        }

        return static::userHasPermission($user, 'sales_book_write');
    }

    private static function hasAnySalesBookPermission(User $user): bool
    {
        $permissions = static::userPermissions($user);

        return in_array('sales_book_read', $permissions, true)
            || in_array('sales_book_comment', $permissions, true)
            || in_array('sales_book_write', $permissions, true);
    }
}
