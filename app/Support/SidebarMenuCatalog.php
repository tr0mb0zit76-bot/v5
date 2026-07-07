<?php

namespace App\Support;

use App\Models\User;

/**
 * Закрепляемые пункты бокового меню CRM (ключи совпадают с CrmLayout MENU_ROUTES).
 */
final class SidebarMenuCatalog
{
    public const MAX_FAVORITES = 5;

    /**
     * @return array<string, string>
     */
    public static function routes(): array
    {
        return [
            'dashboard' => '/dashboard',
            'leads' => '/leads',
            'orders' => '/orders',
            'load-board' => '/load-board',
            'tasks' => '/tasks',
            'kanban' => '/kanban',
            'disposition' => '/disposition',
            'pipeline' => '/pipeline',
            'company-planning' => '/company-planning',
            'orders-create' => '/orders/create',
            'contractors' => '/contractors',
            'fleet-vehicles' => '/fleet/vehicles',
            'fleet-trips' => '/fleet/trips',
            'fleet-efficiency' => '/fleet/efficiency',
            'fleet-containers' => '/fleet/containers',
            'fleet-drivers' => '/drivers',
            'documents' => '/documents',
            'mail' => '/mail',
            'finance' => '/finance',
            'finance-cashflow' => '/finance?section=cashflow',
            'finance-reconciliation' => '/finance/reconciliation',
            'finance-salary' => '/finance/salary',
            'finance-budgeting' => '/budgeting',
            'finance-management-accounting' => '/finance/management-accounting',
            'reports' => '/reports',
            'reports-overview' => '/reports',
            'trainer' => '/sales-assistant/trainer',
            'modules' => '/modules',
            'sales-assistant-counter' => '/sales-assistant/counter',
            'modules-how-much-fits' => '/modules/how-much-fits',
            'modules-how-much-costs' => '/modules/how-much-costs',
            'modules-import-cost' => '/modules/import-cost',
            'sales-assistant-scripts' => '/scripts',
            'sales-assistant-book' => '/sales-assistant/book',
            'sales-assistant-book-quiz-analytics' => '/sales-assistant/book/quiz-analytics',
            'sales-assistant-trainer' => '/sales-assistant/trainer',
            'sales-assistant-trainer-analytics' => '/sales-assistant/trainer/analytics',
            'settings' => '/settings',
            'users' => '/settings/users',
            'roles' => '/settings/roles',
            'business-processes' => '/settings/business-processes',
            'table-presets' => '/settings/tables',
            'dictionaries' => '/settings/dictionaries',
            'templates' => '/settings/templates',
            'mcp-integrations' => '/settings/mcp-integrations',
            'motivation' => '/settings/motivation',
            'kpi-settings' => '/settings/motivation/kpi',
            'salary-settings' => '/settings/motivation/salary',
            'ai-analytics' => '/settings/ai-analytics',
            'system' => '/settings/system',
            'order-numbering' => '/settings/system/order-numbering',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'dashboard' => 'Дашборд',
            'leads' => 'Лиды',
            'orders' => 'Заказы',
            'load-board' => 'Биржа грузов',
            'tasks' => 'Задачи',
            'kanban' => 'Канбан',
            'disposition' => 'Диспозиция',
            'pipeline' => 'Pipeline',
            'company-planning' => 'План компании',
            'orders-create' => 'Новый заказ',
            'contractors' => 'Контрагенты',
            'fleet-vehicles' => 'Авто',
            'fleet-trips' => 'Рейсы',
            'fleet-efficiency' => 'Эффективность',
            'fleet-containers' => 'Контейнера',
            'fleet-drivers' => 'Водители',
            'documents' => 'Документы',
            'mail' => 'Почта',
            'finance' => 'Финансы',
            'finance-cashflow' => 'График оплат',
            'finance-reconciliation' => 'Акты сверок',
            'finance-salary' => 'Зарплата',
            'finance-budgeting' => 'Бюджетирование',
            'finance-management-accounting' => 'Управленческий учёт',
            'reports' => 'Отчёты',
            'reports-overview' => 'Сводные отчёты',
            'trainer' => 'Тренажёр',
            'modules' => 'Модули',
            'sales-assistant-counter' => 'Считалка',
            'modules-how-much-fits' => 'Сколько влезет?',
            'modules-how-much-costs' => 'Сколько стоит?',
            'modules-import-cost' => 'Растаможка',
            'sales-assistant-scripts' => 'Скрипты',
            'sales-assistant-book' => 'Книга продаж',
            'sales-assistant-book-quiz-analytics' => 'Статистика тестов',
            'sales-assistant-trainer' => 'Тренажёр',
            'sales-assistant-trainer-analytics' => 'Аналитика тренажёра',
            'settings' => 'Настройки',
            'users' => 'Пользователи',
            'roles' => 'Роли',
            'business-processes' => 'Бизнес-процессы',
            'table-presets' => 'Управление таблицами',
            'dictionaries' => 'Справочники',
            'templates' => 'Шаблоны',
            'mcp-integrations' => 'Связи MCP',
            'motivation' => 'Мотивация',
            'kpi-settings' => 'Настройки вычетов',
            'salary-settings' => 'Персональные условия',
            'ai-analytics' => 'Аналитика AI',
            'system' => 'Системные',
            'order-numbering' => 'Автонумератор',
        ];
    }

    public static function maxFavorites(): int
    {
        return self::MAX_FAVORITES;
    }

    /**
     * @return list<string>
     */
    public static function validKeys(): array
    {
        return array_keys(self::routes());
    }

    /**
     * Ключи меню, которые пользователь может закрепить (как видимость в CrmLayout).
     *
     * @return list<string>
     */
    public static function candidateKeysForUser(User $user): array
    {
        $out = [];

        foreach (self::validKeys() as $key) {
            if (self::isKeyAccessible($key, $user)) {
                $out[] = $key;
            }
        }

        return $out;
    }

    public static function isKeyAccessible(string $key, User $user): bool
    {
        if (! isset(self::routes()[$key])) {
            return false;
        }

        if (RoleAccess::userHasRoleName($user, 'admin')) {
            return true;
        }

        $areas = RoleAccess::userVisibilityAreas($user);

        return self::isKeyAccessibleForAreas($key, $areas, $user);
    }

    /**
     * @param  list<string>  $areas
     */
    public static function isKeyAccessibleForAreas(string $key, array $areas, User $user): bool
    {
        $areaSet = array_flip($areas);

        return match ($key) {
            'dashboard' => isset($areaSet['dashboard']),
            'leads' => isset($areaSet['leads']),
            'orders', 'disposition', 'orders-create' => isset($areaSet['orders']),
            'load-board' => isset($areaSet['load_board']),
            'tasks' => isset($areaSet['tasks']),
            'kanban' => isset($areaSet['kanban']) || isset($areaSet['tasks']),
            'pipeline' => isset($areaSet['pipeline']),
            'company-planning' => isset($areaSet['company_planning']) && $user->belongsToManagement(),
            'contractors' => isset($areaSet['contractors']),
            'fleet-vehicles', 'fleet-containers', 'fleet-drivers' => isset($areaSet['drivers']),
            'fleet-trips' => RoleAccess::hasOwnFleetSubmoduleAccess($areas, 'fleet_trips'),
            'fleet-efficiency' => RoleAccess::hasOwnFleetSubmoduleAccess($areas, 'fleet_efficiency'),
            'documents' => isset($areaSet['documents']),
            'mail' => isset($areaSet['mail']),
            'finance' => self::hasAnyFinanceRoute($areas, $user),
            'finance-cashflow', 'finance-reconciliation' => isset($areaSet['documents']) || isset($areaSet['payment_schedules']),
            'finance-salary' => isset($areaSet['finance_salary']),
            'finance-budgeting' => $user->belongsToManagement(),
            'finance-management-accounting' => $user->canManagementAccounting(),
            'reports' => self::hasAnyReportsRoute($areas, $user),
            'reports-overview' => isset($areaSet['reports']),
            'ai-analytics' => self::hasSettingsSystemAccess($areas),
            'sales-assistant-trainer-analytics' => RoleAccess::hasSalesAssistantSubmoduleAccess($areas, 'sales_assistant_trainer_analytics'),
            'sales-assistant-book-quiz-analytics' => RoleAccess::hasSalesAssistantSubmoduleAccess($areas, 'sales_assistant_book_analytics'),
            'trainer', 'sales-assistant-trainer' => RoleAccess::hasSalesAssistantSubmoduleAccess($areas, 'sales_assistant_trainer'),
            'sales-assistant-scripts' => RoleAccess::hasSalesAssistantSubmoduleAccess($areas, 'sales_assistant_scripts'),
            'sales-assistant-book' => RoleAccess::hasSalesAssistantSubmoduleAccess($areas, 'sales_assistant_book'),
            'sales-assistant-counter' => RoleAccess::hasSalesAssistantSubmoduleAccess($areas, 'sales_assistant_counter'),
            'modules' => self::hasAnyModulesRoute($areas),
            'modules-how-much-fits' => isset($areaSet['modules']) || isset($areaSet['modules_how_much_fits']),
            'modules-how-much-costs' => isset($areaSet['modules']) || isset($areaSet['modules_how_much_costs']),
            'modules-import-cost' => isset($areaSet['modules']) || isset($areaSet['modules_import_cost']),
            'settings' => self::hasSettingsSystemAccess($areas) || self::hasSettingsMotivationAccess($areas),
            'users', 'business-processes', 'table-presets', 'dictionaries', 'templates', 'mcp-integrations', 'system', 'order-numbering', 'ai-analytics' => self::hasSettingsSystemAccess($areas),
            'roles' => false,
            'motivation', 'kpi-settings', 'salary-settings' => self::hasSettingsMotivationAccess($areas),
            default => false,
        };
    }

    /**
     * @param  list<string>  $areas
     */
    private static function hasLegacyAllSettingsAccess(array $areas): bool
    {
        return in_array('settings', $areas, true)
            && ! in_array('settings_system', $areas, true)
            && ! in_array('settings_motivation', $areas, true);
    }

    /**
     * @param  list<string>  $areas
     */
    private static function hasSettingsSystemAccess(array $areas): bool
    {
        return self::hasLegacyAllSettingsAccess($areas) || in_array('settings_system', $areas, true);
    }

    /**
     * @param  list<string>  $areas
     */
    private static function hasSettingsMotivationAccess(array $areas): bool
    {
        return self::hasLegacyAllSettingsAccess($areas) || in_array('settings_motivation', $areas, true);
    }

    /**
     * @param  list<string>  $areas
     */
    private static function hasAnyFinanceRoute(array $areas, User $user): bool
    {
        return self::isKeyAccessibleForAreas('finance-cashflow', $areas, $user)
            || self::isKeyAccessibleForAreas('finance-reconciliation', $areas, $user)
            || self::isKeyAccessibleForAreas('finance-salary', $areas, $user)
            || self::isKeyAccessibleForAreas('finance-budgeting', $areas, $user)
            || self::isKeyAccessibleForAreas('finance-management-accounting', $areas, $user);
    }

    /**
     * @param  list<string>  $areas
     */
    private static function hasAnyReportsRoute(array $areas, User $user): bool
    {
        return self::isKeyAccessibleForAreas('reports-overview', $areas, $user)
            || self::isKeyAccessibleForAreas('ai-analytics', $areas, $user)
            || self::isKeyAccessibleForAreas('sales-assistant-trainer-analytics', $areas, $user)
            || self::isKeyAccessibleForAreas('sales-assistant-book-quiz-analytics', $areas, $user);
    }

    /**
     * @param  list<string>  $areas
     */
    private static function hasAnyModulesRoute(array $areas): bool
    {
        return in_array('modules', $areas, true)
            || in_array('modules_how_much_fits', $areas, true)
            || in_array('modules_how_much_costs', $areas, true)
            || in_array('modules_import_cost', $areas, true);
    }
}
