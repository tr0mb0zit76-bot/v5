<?php

namespace App\Support;

/**
 * Каталог логических доменов CRM для настройки обмена данными между MCP-инструментами.
 */
final class McpIntegrationCatalog
{
    /**
     * @return list<array{key: string, label: string, description: string, group: string}>
     */
    public static function nodes(): array
    {
        return [
            ['key' => 'orders', 'label' => 'Заказы', 'description' => 'Карточки заказов, маршрут, документы', 'group' => 'Продажи'],
            ['key' => 'leads', 'label' => 'Лиды', 'description' => 'Воронка до конверсии', 'group' => 'Продажи'],
            ['key' => 'contractors', 'label' => 'Контрагенты', 'description' => 'Справочник контрагентов', 'group' => 'Продажи'],
            ['key' => 'sales_book', 'label' => 'Книга продаж', 'description' => 'Статьи, тесты, качество контента', 'group' => 'Помощник продавца'],
            ['key' => 'trainer', 'label' => 'Тренажёр', 'description' => 'Сценарии и коучинг', 'group' => 'Помощник продавца'],
            ['key' => 'tasks', 'label' => 'Задачи', 'description' => 'Внутренние и клиентские задачи', 'group' => 'Операции'],
            ['key' => 'disposition', 'label' => 'Диспозиция', 'description' => 'Утро/вечер по заказам', 'group' => 'Операции'],
            ['key' => 'mail', 'label' => 'Почта', 'description' => 'Переписка и исходящие письма', 'group' => 'Коммуникации'],
            ['key' => 'fleet', 'label' => 'Собственный парк', 'description' => 'Авто, водители, рейсы', 'group' => 'Транспорт'],
            ['key' => 'finance', 'label' => 'Финансы', 'description' => 'График оплат и зарплата', 'group' => 'Финансы'],
            ['key' => 'analytics', 'label' => 'Аналитика AI', 'description' => 'Обращения к AI и пробелы', 'group' => 'Отчёты'],
            ['key' => 'settings', 'label' => 'Шаблоны и система', 'description' => 'Печатные формы, базовые условия, конфигурация', 'group' => 'Конфигурация'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function nodeKeys(): array
    {
        return array_column(self::nodes(), 'key');
    }

    public static function isKnownNode(string $key): bool
    {
        return in_array($key, self::nodeKeys(), true);
    }

    /**
     * @return array<string, array{key: string, label: string, description: string, group: string}>|null
     */
    public static function nodeByKey(string $key): ?array
    {
        foreach (self::nodes() as $node) {
            if ($node['key'] === $key) {
                return $node;
            }
        }

        return null;
    }
}
