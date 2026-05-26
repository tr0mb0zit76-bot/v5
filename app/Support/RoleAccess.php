<?php

namespace App\Support;

use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Schema;

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
            ['key' => 'manage_payment_schedules', 'label' => 'График оплат: полные действия', 'description' => 'Фиксация оплат, отмена/восстановление строк, правка номера счёта. Либо включите это право, либо отдельные пункты «Зафиксировать платёж» и «Отмена» ниже'],
            ['key' => 'payment_schedule_record_payment', 'label' => 'График оплат: зафиксировать платёж', 'description' => 'Кнопка «Зафиксировать платёж» и сохранение фактической оплаты по строке графика'],
            ['key' => 'payment_schedule_cancel_row', 'label' => 'График оплат: отмена и восстановление', 'description' => 'Кнопки «Отменить» и «Восстановить» по строке графика'],
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
            ['key' => 'leads', 'label' => 'Лиды', 'description' => 'Воронка до конверсии в заказ'],
            ['key' => 'mail', 'label' => 'Почта', 'description' => 'Исходящая переписка с клиентами, отправка КП'],
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
            ['key' => 'modules', 'label' => 'Модули', 'description' => 'Каталог доступных модулей; при выборе компонентов уточните строки ниже'],
            ['key' => 'modules_catalog', 'label' => 'Модули: каталог', 'description' => 'Страница со списком модулей'],
            ['key' => 'modules_how_much_fits', 'label' => 'Модули: «Сколько влезет?»', 'description' => '3D-планировщик загрузки транспорта'],
            ['key' => 'modules_how_much_costs', 'label' => 'Модули: «Сколько стоит?»', 'description' => 'Калькулятор маржи и дельты сделки'],
            ['key' => 'scripts', 'label' => 'Помощник продавца', 'description' => 'Общий доступ к модулю; при выборе компонентов уточните строки ниже'],
            ['key' => 'sales_assistant_scripts', 'label' => 'Помощник продавца: скрипты', 'description' => 'Список сценариев и прохождение шагов (в т.ч. из тренажёра)'],
            ['key' => 'sales_assistant_book', 'label' => 'Помощник продавца: книга продаж', 'description' => 'База знаний и статьи'],
            ['key' => 'sales_assistant_trainer', 'label' => 'Помощник продавца: тренажёр', 'description' => 'Запуск тренировок по сценариям'],
            ['key' => 'sales_assistant_trainer_analytics', 'label' => 'Помощник продавца: аналитика тренажёра', 'description' => 'Сводки и отчёты по тренировкам'],
            ['key' => 'sales_assistant_counter', 'label' => 'Помощник продавца: считалка', 'description' => 'Калькулятор маржи и ставок для переговоров'],
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
            'supervisor' => ['dashboard', 'dashboard_tiles', 'leads', 'orders', 'scripts', 'users', 'contractors', 'drivers', 'documents', 'finance_salary', 'payment_schedules', 'tasks', 'kanban', 'reports', 'settings_motivation'],
            'manager' => ['dashboard', 'dashboard_tiles', 'leads', 'orders', 'scripts', 'contractors', 'documents', 'payment_schedules', 'tasks', 'kanban', 'reports'],
            'dispatcher' => ['dashboard', 'dashboard_tiles', 'orders', 'scripts', 'drivers', 'payment_schedules', 'tasks', 'kanban'],
            'accountant' => ['dashboard', 'dashboard_tiles', 'orders', 'documents', 'finance_salary', 'payment_schedules', 'tasks', 'kanban', 'reports'],
            'clerk' => ['dashboard', 'dashboard_tiles', 'orders', 'scripts', 'documents', 'contractors', 'payment_schedules', 'tasks', 'kanban'],
            'viewer' => ['dashboard', 'dashboard_tiles', 'orders'],
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
            ],
            'dispatcher' => [
                'orders' => 'all',
                'tasks' => 'all',
                'kanban' => 'all',
                'payment_schedules' => 'all',
                'dashboard_tiles' => 'all',
            ],
            'accountant' => [
                'orders' => 'all',
                'tasks' => 'all',
                'kanban' => 'all',
                'documents' => 'all',
                'payment_schedules' => 'all',
                'dashboard_tiles' => 'all',
            ],
            'clerk' => [
                'orders' => 'all',
                'tasks' => 'all',
                'kanban' => 'all',
                'contractors' => 'all',
                'documents' => 'all',
                'payment_schedules' => 'all',
                'dashboard_tiles' => 'all',
            ],
            'viewer' => [
                'orders' => 'all',
                'dashboard_tiles' => 'all',
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

        if ($user->isAdmin()) {
            return 'all';
        }

        $scopes = static::mergedVisibilityScopesForUser($user);
        $fallbackRoleName = static::assignedRoles($user)->first()?->name;

        $value = $scopes[$area] ?? static::defaultVisibilityScopes($fallbackRoleName)[$area] ?? 'own';

        return in_array($value, ['own', 'all'], true) ? $value : 'own';
    }

    /**
     * @return EloquentCollection<int, Role>
     */
    public static function assignedRoles(User $user): EloquentCollection
    {
        $user->loadMissing(['roles', 'role']);

        if ($user->relationLoaded('roles') && $user->roles->isNotEmpty()) {
            return $user->roles;
        }

        if (! Schema::hasTable('roles')) {
            return new EloquentCollection;
        }

        if ($user->role_id !== null && $user->role !== null) {
            return new EloquentCollection([$user->role]);
        }

        if ($user->role_id !== null) {
            $role = Role::query()->find($user->role_id);

            return $role !== null ? new EloquentCollection([$role]) : new EloquentCollection;
        }

        return new EloquentCollection;
    }

    public static function userHasRoleName(?User $user, string $roleName): bool
    {
        if ($user === null) {
            return false;
        }

        return static::assignedRoles($user)->contains(
            fn (Role $role): bool => $role->name === $roleName,
        );
    }

    /**
     * @return array<string, string>
     */
    public static function mergedVisibilityScopesForUser(User $user): array
    {
        $merged = [];

        foreach (static::assignedRoles($user) as $role) {
            $scopes = static::coerceVisibilityScopes($role->visibility_scopes)
                ?? static::defaultVisibilityScopes($role->name);

            foreach ($scopes as $area => $value) {
                if (! is_string($area) || $area === '') {
                    continue;
                }

                if (($merged[$area] ?? 'own') === 'all' || $value === 'all') {
                    $merged[$area] = 'all';

                    continue;
                }

                $merged[$area] = $merged[$area] ?? 'own';
            }
        }

        return $merged;
    }

    /**
     * @return list<string>
     */
    public static function userVisibilityAreas(User $user): array
    {
        $merged = [];

        foreach (static::assignedRoles($user) as $role) {
            $areas = static::effectiveVisibilityAreasFromRolePayload($role->name, $role->visibility_areas);
            $merged = [...$merged, ...$areas];
        }

        return array_values(array_unique($merged));
    }

    /**
     * @return list<string>
     */
    public static function userRoleIds(User $user): array
    {
        return static::assignedRoles($user)->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
    }

    /**
     * @param  list<int>  $roleIds
     */
    public static function syncUserRoles(User $user, array $roleIds): void
    {
        $normalized = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $roleIds),
            static fn (int $id): bool => $id > 0,
        )));

        if (Schema::hasTable('role_user')) {
            $user->roles()->sync($normalized);
        }

        $user->forceFill(['role_id' => $normalized[0] ?? null])->save();
    }

    /**
     * Единые правила: null / не-массив / пустой массив в БД → дефолты по коду роли;
     * легаси «только scripts» раскрывается в подмодули для согласованности с каналом авторизации и меню.
     *
     * @return list<string>
     */
    public static function effectiveVisibilityAreasFromRolePayload(?string $roleName, mixed $raw): array
    {
        $areas = $raw;

        if (is_string($areas)) {
            $decoded = json_decode($areas, true);
            $areas = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($areas) || $areas === []) {
            $areas = static::defaultVisibilityAreas($roleName);
        }

        $filtered = [];

        foreach ($areas as $key) {
            if (is_string($key) && $key !== '') {
                $filtered[] = $key;
            }
        }

        if ($filtered === []) {
            return static::defaultVisibilityAreas($roleName);
        }

        $expanded = static::expandLegacySalesAssistantVisibilityAreas(array_values(array_unique($filtered)));
        $expanded = static::expandLegacyModulesVisibilityAreas($expanded);

        return $expanded !== []
            ? $expanded
            : static::defaultVisibilityAreas($roleName);
    }

    /**
     * @return list<string>
     */
    public static function modulesComponentKeys(): array
    {
        return [
            'modules_catalog',
            'modules_how_much_fits',
            'modules_how_much_costs',
        ];
    }

    /**
     * @param  list<string>  $areas
     * @return list<string>
     */
    public static function expandLegacyModulesVisibilityAreas(array $areas): array
    {
        if (! in_array('modules', $areas, true)) {
            return array_values(array_unique($areas));
        }

        foreach (static::modulesComponentKeys() as $key) {
            if (in_array($key, $areas, true)) {
                return array_values(array_unique($areas));
            }
        }

        return array_values(array_unique([...$areas, ...static::modulesComponentKeys()]));
    }

    /**
     * @return list<string>
     */
    public static function salesAssistantComponentKeys(): array
    {
        return [
            'sales_assistant_scripts',
            'sales_assistant_book',
            'sales_assistant_trainer',
            'sales_assistant_trainer_analytics',
            'sales_assistant_counter',
        ];
    }

    /**
     * Для UI ролей: легаси-только «scripts» раскрываем во все подмодули, чтобы чекбоксы совпадали с фактическим доступом.
     *
     * @param  list<string>  $areas
     * @return list<string>
     */
    public static function expandLegacySalesAssistantVisibilityAreas(array $areas): array
    {
        if (! in_array('scripts', $areas, true)) {
            return array_values(array_unique($areas));
        }

        foreach (static::salesAssistantComponentKeys() as $key) {
            if (in_array($key, $areas, true)) {
                return array_values(array_unique($areas));
            }
        }

        return array_values(array_unique([...$areas, ...static::salesAssistantComponentKeys()]));
    }

    public static function hasVisibilityArea(array $areas, string $required): bool
    {
        if (in_array($required, $areas, true)) {
            return true;
        }

        // Тренажёр запускает сессии через тот же pipeline, что «Скрипты» (POST /scripts/sessions, advance, trainer-meta…).
        if ($required === 'sales_assistant_scripts' && in_array('sales_assistant_trainer', $areas, true)) {
            return true;
        }

        $assistantKeys = static::salesAssistantComponentKeys();
        if (in_array($required, $assistantKeys, true) && in_array('scripts', $areas, true)) {
            return true;
        }

        if ($required === 'scripts') {
            foreach ($assistantKeys as $key) {
                if (in_array($key, $areas, true)) {
                    return true;
                }
            }
        }

        $moduleKeys = static::modulesComponentKeys();
        if (in_array($required, $moduleKeys, true) && in_array('modules', $areas, true)) {
            return true;
        }

        if ($required === 'modules') {
            foreach ($moduleKeys as $key) {
                if (in_array($key, $areas, true)) {
                    return true;
                }
            }
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
     * Модуль «Бюджетирование» — только группа «Управление» (флаг пользователя) или admin.
     */
    public static function canAccessBudgeting(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->belongsToManagement();
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
     * @return array{hasPs: bool, hasFs: bool}
     */
    private static function userPaymentScheduleFinanceAreas(?User $user): array
    {
        if ($user === null) {
            return ['hasPs' => false, 'hasFs' => false];
        }

        $areas = static::userVisibilityAreas($user);

        return [
            'hasPs' => static::hasVisibilityArea($areas, 'payment_schedules'),
            'hasFs' => static::hasVisibilityArea($areas, 'finance_salary'),
        ];
    }

    /**
     * Правка номера счёта в графике и прочие «полные» действия, кроме отдельно настраиваемых кнопок.
     * Явное право manage_payment_schedules обязательно, если в роли есть только область «График оплат».
     * Сочетание областей «График оплат» + «Финансы: зарплата» сохраняет прежнее поведение без отдельной галки.
     */
    public static function canManagePaymentSchedules(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $ctx = static::userPaymentScheduleFinanceAreas($user);

        if (! $ctx['hasPs'] && ! $ctx['hasFs']) {
            return false;
        }

        if (static::userHasPermission($user, 'manage_payment_schedules')) {
            return true;
        }

        if ($ctx['hasPs'] && $ctx['hasFs']) {
            return true;
        }

        return ! $ctx['hasPs'] && $ctx['hasFs'];
    }

    /**
     * Кнопка «Зафиксировать платёж» (и сохранение фактической оплаты).
     * Доступно при полном праве manage_payment_schedules, устаревших сочетаниях областей или явной галке.
     */
    public static function canRecordPaymentOnPaymentSchedule(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $ctx = static::userPaymentScheduleFinanceAreas($user);
        if (! $ctx['hasPs'] && ! $ctx['hasFs']) {
            return false;
        }

        if (static::userHasPermission($user, 'manage_payment_schedules')) {
            return true;
        }

        if ($ctx['hasPs'] && $ctx['hasFs']) {
            return true;
        }

        if (! $ctx['hasPs'] && $ctx['hasFs']) {
            return true;
        }

        return static::userHasPermission($user, 'payment_schedule_record_payment');
    }

    /**
     * Кнопки «Отменить» и «Восстановить» по строке графика.
     */
    public static function canCancelPaymentScheduleRow(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $ctx = static::userPaymentScheduleFinanceAreas($user);
        if (! $ctx['hasPs'] && ! $ctx['hasFs']) {
            return false;
        }

        if (static::userHasPermission($user, 'manage_payment_schedules')) {
            return true;
        }

        if ($ctx['hasPs'] && $ctx['hasFs']) {
            return true;
        }

        if (! $ctx['hasPs'] && $ctx['hasFs']) {
            return true;
        }

        return static::userHasPermission($user, 'payment_schedule_cancel_row');
    }

    /**
     * Показывать колонку «Действия» (частично может быть доступна без полного manage).
     */
    public static function canShowPaymentScheduleActionsColumn(?User $user): bool
    {
        return static::canManagePaymentSchedules($user)
            || static::canRecordPaymentOnPaymentSchedule($user)
            || static::canCancelPaymentScheduleRow($user);
    }

    /**
     * @return list<string>
     */
    public static function userPermissions(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $merged = [];

        foreach (static::assignedRoles($user) as $role) {
            $raw = $role->permissions;
            if (! is_array($raw)) {
                continue;
            }

            foreach ($raw as $permission) {
                if (is_string($permission) && $permission !== '') {
                    $merged[] = $permission;
                }
            }
        }

        return array_values(array_unique($merged));
    }

    public static function userHasPermission(?User $user, string $permission): bool
    {
        return in_array($permission, static::userPermissions($user), true);
    }

    public static function canAccessSalesAssistantCounter(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return static::hasVisibilityArea(static::userVisibilityAreas($user), 'sales_assistant_counter');
    }

    public static function canReadSalesBook(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! static::hasVisibilityArea(static::userVisibilityAreas($user), 'sales_assistant_book')) {
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

        if (! static::hasVisibilityArea(static::userVisibilityAreas($user), 'sales_assistant_book')) {
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

        if (! static::hasVisibilityArea(static::userVisibilityAreas($user), 'sales_assistant_book')) {
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
