<?php

namespace App\Support;

/**
 * Ключи нижней навигации PWA (CrmLayout mobile shell).
 */
final class MobileNavCatalog
{
    public const ORDER = ['dashboard', 'orders', 'load-board', 'leads', 'tasks', 'kanban', 'documents', 'reports', 'finance', 'trainer'];

    public const MAX_SELECTABLE = 6;

    public static function maxSelectable(): int
    {
        return self::MAX_SELECTABLE;
    }

    /**
     * @return list<string>
     */
    public static function validKeys(): array
    {
        return self::ORDER;
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'dashboard' => 'Главная',
            'orders' => 'Заказы',
            'load-board' => 'Биржа',
            'leads' => 'Лиды',
            'tasks' => 'Задачи',
            'kanban' => 'Канбан',
            'documents' => 'Документы',
            'reports' => 'Отчёты',
            'finance' => 'Финансы',
            'trainer' => 'Тренажёр',
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function optionsForUi(): array
    {
        $labels = self::labels();
        $out = [];
        foreach (self::ORDER as $key) {
            $out[] = ['key' => $key, 'label' => $labels[$key] ?? $key];
        }

        return $out;
    }

    /**
     * Какие пункты доступны роли с учётом областей видимости (как в CrmLayout).
     *
     * @param  list<string>  $visibleAreas
     * @return list<string>
     */
    public static function candidateKeys(bool $isAdmin, array $visibleAreas): array
    {
        $areaSet = array_flip($visibleAreas);

        $out = [];
        foreach (self::ORDER as $key) {
            if ($isAdmin) {
                $out[] = $key;

                continue;
            }

            if ($key === 'dashboard') {
                if (isset($areaSet['dashboard'])) {
                    $out[] = $key;
                }

                continue;
            }

            if ($key === 'kanban') {
                if (isset($areaSet['kanban']) || isset($areaSet['tasks'])) {
                    $out[] = $key;
                }

                continue;
            }

            if ($key === 'trainer') {
                if (isset($areaSet['sales_assistant_trainer']) || isset($areaSet['scripts'])) {
                    $out[] = $key;
                }

                continue;
            }

            if ($key === 'finance') {
                if (isset($areaSet['finance'])
                    || isset($areaSet['finance_salary'])
                    || isset($areaSet['budgeting'])) {
                    $out[] = $key;
                }

                continue;
            }

            $required = match ($key) {
                'orders' => 'orders',
                'load-board' => 'load_board',
                'leads' => 'leads',
                'tasks' => 'tasks',
                'documents' => 'documents',
                'reports' => 'reports',
                default => null,
            };

            if ($required !== null && isset($areaSet[$required])) {
                $out[] = $key;
            }
        }

        return array_values(array_unique($out));
    }
}
