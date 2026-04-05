<?php

namespace App\Support;

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
            ['key' => 'leads', 'label' => 'Лиды', 'description' => 'Воронка до конверсии в заказ'],
            ['key' => 'orders', 'label' => 'Заказы', 'description' => 'Раздел работы с заказами'],
            ['key' => 'users', 'label' => 'Пользователи', 'description' => 'Управление пользователями'],
            ['key' => 'roles', 'label' => 'Роли', 'description' => 'Управление ролями и правами'],
            ['key' => 'contractors', 'label' => 'Контрагенты', 'description' => 'Справочник контрагентов'],
            ['key' => 'drivers', 'label' => 'Водители', 'description' => 'Реестр водителей и перевозчиков'],
            ['key' => 'documents', 'label' => 'Документы', 'description' => 'Реестр документов'],
            ['key' => 'activities', 'label' => 'Активности', 'description' => 'История действий и событий'],
            ['key' => 'tasks', 'label' => 'Задачи', 'description' => 'Управление внутренними и клиентскими задачами'],
            ['key' => 'kanban', 'label' => 'Канбан', 'description' => 'Визуальная доска задач'],
            ['key' => 'reports', 'label' => 'Отчеты', 'description' => 'Финансовые и операционные отчеты'],
            ['key' => 'modules', 'label' => 'Модули', 'description' => 'Каталог доступных модулей'],
            ['key' => 'settings', 'label' => 'Настройки', 'description' => 'Системные настройки'],
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
            'supervisor' => ['dashboard', 'leads', 'orders', 'users', 'contractors', 'drivers', 'documents', 'activities', 'tasks', 'kanban', 'reports', 'settings'],
            'manager' => ['dashboard', 'leads', 'orders', 'contractors', 'documents', 'activities', 'tasks', 'kanban'],
            'dispatcher' => ['dashboard', 'orders', 'drivers', 'activities', 'tasks', 'kanban'],
            'accountant' => ['dashboard', 'orders', 'documents', 'tasks', 'kanban', 'reports'],
            'clerk' => ['dashboard', 'orders', 'documents', 'contractors', 'tasks', 'kanban'],
            'viewer' => ['dashboard', 'orders'],
            default => ['dashboard'],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function defaultVisibilityScopes(?string $roleName): array
    {
        return match ($roleName) {
            'admin' => ['orders' => 'all', 'leads' => 'all'],
            'supervisor' => ['orders' => 'all', 'leads' => 'all'],
            'manager' => ['orders' => 'own', 'leads' => 'own'],
            'dispatcher' => ['orders' => 'all'],
            'accountant' => ['orders' => 'all'],
            'clerk' => ['orders' => 'all'],
            'viewer' => ['orders' => 'all'],
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
}
