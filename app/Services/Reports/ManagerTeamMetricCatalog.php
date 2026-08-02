<?php

declare(strict_types=1);

namespace App\Services\Reports;

/**
 * Каталог метрик отчёта «Команда / Менеджеры».
 *
 * Режимы: snapshot (воронка сейчас), period (результаты), compare (период к периоду).
 * Design: JSON/Inertia — отчёт считается только при tab=managers; фильтры mode, user_ids[],
 * department_id, metrics[] (группы или ключи); partial reload only team_report.
 */
final class ManagerTeamMetricCatalog
{
    public const MODE_SNAPSHOT = 'snapshot';

    public const MODE_PERIOD = 'period';

    public const MODE_COMPARE = 'compare';

    public const GROUP_LEADS = 'leads';

    public const GROUP_ORDERS_VOLUME = 'orders_volume';

    public const GROUP_ORDERS_MONEY = 'orders_money';

    public const GROUP_TASKS = 'tasks';

    public const GROUP_FUNNEL_RISKS = 'funnel_risks';

    /**
     * @var list<string>
     */
    public const ORDER_STATUSES = [
        'new',
        'in_progress',
        'documents',
        'payment',
        'closed',
        'cancelled',
        'disruption',
    ];

    /**
     * @var list<string>
     */
    public const PIPELINE_OPEN_STATUSES = [
        'new',
        'in_progress',
        'documents',
        'payment',
    ];

    /**
     * @return list<string>
     */
    public static function modes(): array
    {
        return [self::MODE_SNAPSHOT, self::MODE_PERIOD, self::MODE_COMPARE];
    }

    /**
     * @return array<string, array{label: string, modes: list<string>}>
     */
    public static function groups(): array
    {
        return [
            self::GROUP_LEADS => [
                'label' => 'Лиды',
                'modes' => self::modes(),
            ],
            self::GROUP_ORDERS_VOLUME => [
                'label' => 'Заказы (объём)',
                'modes' => self::modes(),
            ],
            self::GROUP_ORDERS_MONEY => [
                'label' => 'Деньги',
                'modes' => self::modes(),
            ],
            self::GROUP_TASKS => [
                'label' => 'Задачи',
                'modes' => self::modes(),
            ],
            self::GROUP_FUNNEL_RISKS => [
                'label' => 'Риски воронки',
                'modes' => [self::MODE_SNAPSHOT],
            ],
        ];
    }

    /**
     * @return list<array{
     *     key: string,
     *     group: string,
     *     label: string,
     *     modes: list<string>,
     *     format: 'int'|'money'|'percent'
     * }>
     */
    public static function definitions(): array
    {
        $defs = [
            [
                'key' => 'leads_open',
                'group' => self::GROUP_LEADS,
                'label' => 'Лиды открытые',
                'modes' => [self::MODE_SNAPSHOT],
                'format' => 'int',
            ],
            [
                'key' => 'leads_created',
                'group' => self::GROUP_LEADS,
                'label' => 'Лиды создано',
                'modes' => [self::MODE_PERIOD, self::MODE_COMPARE],
                'format' => 'int',
            ],
            [
                'key' => 'leads_won',
                'group' => self::GROUP_LEADS,
                'label' => 'Лиды выиграно',
                'modes' => [self::MODE_PERIOD, self::MODE_COMPARE],
                'format' => 'int',
            ],
            [
                'key' => 'leads_lost',
                'group' => self::GROUP_LEADS,
                'label' => 'Лиды проиграно',
                'modes' => [self::MODE_PERIOD, self::MODE_COMPARE],
                'format' => 'int',
            ],
            [
                'key' => 'leads_win_rate',
                'group' => self::GROUP_LEADS,
                'label' => 'Win rate, %',
                'modes' => [self::MODE_PERIOD, self::MODE_COMPARE],
                'format' => 'percent',
            ],
            [
                'key' => 'orders_open_count',
                'group' => self::GROUP_ORDERS_VOLUME,
                'label' => 'Заказы незакрытые',
                'modes' => [self::MODE_SNAPSHOT],
                'format' => 'int',
            ],
            [
                'key' => 'orders_created',
                'group' => self::GROUP_ORDERS_VOLUME,
                'label' => 'Заказы создано',
                'modes' => [self::MODE_PERIOD, self::MODE_COMPARE],
                'format' => 'int',
            ],
            [
                'key' => 'orders_closed',
                'group' => self::GROUP_ORDERS_VOLUME,
                'label' => 'Заказы закрыто',
                'modes' => [self::MODE_PERIOD, self::MODE_COMPARE],
                'format' => 'int',
            ],
            [
                'key' => 'money_pipeline_revenue',
                'group' => self::GROUP_ORDERS_MONEY,
                'label' => 'Выручка в трубе',
                'modes' => [self::MODE_SNAPSHOT],
                'format' => 'money',
            ],
            [
                'key' => 'money_pipeline_margin',
                'group' => self::GROUP_ORDERS_MONEY,
                'label' => 'Маржа в трубе',
                'modes' => [self::MODE_SNAPSHOT],
                'format' => 'money',
            ],
            [
                'key' => 'money_closed_revenue',
                'group' => self::GROUP_ORDERS_MONEY,
                'label' => 'Выручка закрытых',
                'modes' => [self::MODE_PERIOD, self::MODE_COMPARE],
                'format' => 'money',
            ],
            [
                'key' => 'money_closed_margin',
                'group' => self::GROUP_ORDERS_MONEY,
                'label' => 'Маржа закрытых',
                'modes' => [self::MODE_PERIOD, self::MODE_COMPARE],
                'format' => 'money',
            ],
            [
                'key' => 'money_closed_avg_check',
                'group' => self::GROUP_ORDERS_MONEY,
                'label' => 'Средний чек',
                'modes' => [self::MODE_PERIOD, self::MODE_COMPARE],
                'format' => 'money',
            ],
            [
                'key' => 'tasks_open',
                'group' => self::GROUP_TASKS,
                'label' => 'Задачи открытые',
                'modes' => [self::MODE_SNAPSHOT],
                'format' => 'int',
            ],
            [
                'key' => 'tasks_overdue',
                'group' => self::GROUP_TASKS,
                'label' => 'Задачи просроченные',
                'modes' => [self::MODE_SNAPSHOT],
                'format' => 'int',
            ],
            [
                'key' => 'tasks_created',
                'group' => self::GROUP_TASKS,
                'label' => 'Задачи создано',
                'modes' => [self::MODE_PERIOD, self::MODE_COMPARE],
                'format' => 'int',
            ],
            [
                'key' => 'tasks_done',
                'group' => self::GROUP_TASKS,
                'label' => 'Задачи выполнено',
                'modes' => [self::MODE_PERIOD, self::MODE_COMPARE],
                'format' => 'int',
            ],
            [
                'key' => 'leads_stuck',
                'group' => self::GROUP_FUNNEL_RISKS,
                'label' => 'Лиды застряли',
                'modes' => [self::MODE_SNAPSHOT],
                'format' => 'int',
            ],
            [
                'key' => 'leads_sla_overdue',
                'group' => self::GROUP_FUNNEL_RISKS,
                'label' => 'Лиды SLA',
                'modes' => [self::MODE_SNAPSHOT],
                'format' => 'int',
            ],
        ];

        $statusLabels = [
            'new' => 'Новый',
            'in_progress' => 'Выполняется',
            'documents' => 'Документы',
            'payment' => 'Оплата',
            'closed' => 'Завершено',
            'cancelled' => 'Отменена',
            'disruption' => 'Срыв',
        ];

        foreach (self::ORDER_STATUSES as $status) {
            $defs[] = [
                'key' => 'orders_by_status.'.$status,
                'group' => self::GROUP_ORDERS_VOLUME,
                'label' => 'Заказы: '.$statusLabels[$status],
                'modes' => [self::MODE_SNAPSHOT],
                'format' => 'int',
            ];
        }

        return $defs;
    }

    /**
     * @return list<string>
     */
    public static function defaultMetricKeys(string $mode): array
    {
        return match ($mode) {
            self::MODE_SNAPSHOT => [
                'leads_open',
                'leads_stuck',
                'leads_sla_overdue',
                'orders_open_count',
                'orders_by_status.new',
                'orders_by_status.in_progress',
                'orders_by_status.documents',
                'orders_by_status.payment',
                'money_pipeline_revenue',
                'money_pipeline_margin',
                'tasks_open',
                'tasks_overdue',
            ],
            self::MODE_COMPARE, self::MODE_PERIOD => [
                'leads_created',
                'leads_won',
                'leads_lost',
                'leads_win_rate',
                'orders_created',
                'orders_closed',
                'money_closed_margin',
                'money_closed_avg_check',
                'tasks_created',
                'tasks_done',
            ],
            default => [],
        };
    }

    /**
     * @param  list<string>  $requested  Group keys and/or metric keys
     * @return list<string>
     */
    public static function resolveMetricKeys(string $mode, array $requested): array
    {
        $defs = collect(self::definitions())
            ->filter(fn (array $def): bool => in_array($mode, $def['modes'], true))
            ->values();

        if ($requested === []) {
            return array_values(array_filter(
                self::defaultMetricKeys($mode),
                fn (string $key): bool => $defs->contains(fn (array $def): bool => $def['key'] === $key),
            ));
        }

        $groupKeys = array_keys(self::groups());
        $selected = [];

        foreach ($requested as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }

            if (in_array($token, $groupKeys, true)) {
                foreach ($defs as $def) {
                    if ($def['group'] === $token) {
                        $selected[] = $def['key'];
                    }
                }

                continue;
            }

            if ($defs->contains(fn (array $def): bool => $def['key'] === $token)) {
                $selected[] = $token;
            }
        }

        if ($selected === []) {
            return self::resolveMetricKeys($mode, []);
        }

        return array_values(array_unique($selected));
    }

    /**
     * @param  list<string>  $metricKeys
     * @return list<array{key: string, group: string, label: string, format: string}>
     */
    public static function columnsFor(array $metricKeys): array
    {
        $byKey = collect(self::definitions())->keyBy('key');

        $columns = [];
        foreach ($metricKeys as $key) {
            $def = $byKey->get($key);
            if (! is_array($def)) {
                continue;
            }

            $columns[] = [
                'key' => $def['key'],
                'group' => $def['group'],
                'label' => $def['label'],
                'format' => $def['format'],
                'drilldown' => true,
            ];
        }

        return $columns;
    }

    public static function glossaryForMode(string $mode): string
    {
        return match ($mode) {
            self::MODE_SNAPSHOT => 'Воронка сейчас: снимок на момент запроса. Даты периода не влияют. Открытые лиды, заказы по статусам, деньги в незакрытых заказах (customer_rate / delta), открытые и просроченные задачи, риски этапов БП.',
            self::MODE_COMPARE => 'Период к периоду: те же метрики результатов за выбранный период и автоматически вычисленный предыдущий интервал той же длины. Δ% = н/д, если предыдущее значение 0. Выигранные/проигранные лиды — по updated_at при статусе won/lost.',
            default => 'Результаты периода: лиды созданы по created_at; won/lost — по updated_at при terminal-статусе; заказы созданы по order_date; закрытые заказы и маржа — по дате закрытия (как раньше). Задачи — created_at / completed_at.',
        };
    }
}
