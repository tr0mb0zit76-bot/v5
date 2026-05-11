<?php

namespace App\Services\Reports;

use App\Services\CompletedOrderFinancialAnalytics;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Сводные отчёты для финдира / руководителя продаж: ABC, XYZ, статистика по менеджерам.
 */
class FinancialReportsService
{
    public function __construct(
        private readonly CompletedOrderFinancialAnalytics $completedOrderFinancialAnalytics,
    ) {}

    /**
     * ABC по выручке (ставка клиента по заказам) за период.
     *
     * @return array{
     *     rows: list<array{
     *         customer_id: int,
     *         customer_name: string,
     *         revenue: float,
     *         orders_count: int,
     *         share_percent: float,
     *         cumulative_share_percent: float,
     *         abc_class: 'A'|'B'|'C'
     *     }>,
     *     total_revenue: float,
     *     total_orders: int
     * }
     */
    public function abcByCustomer(Carbon $from, Carbon $to, ?int $managerId): array
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'customer_rate')) {
            return ['rows' => [], 'total_revenue' => 0.0, 'total_orders' => 0];
        }

        $query = DB::table('orders')
            ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()])
            ->when(
                Schema::hasColumn('orders', 'deleted_at'),
                fn ($q) => $q->whereNull('orders.deleted_at'),
            )
            ->when(
                Schema::hasColumn('orders', 'customer_id'),
                fn ($q) => $q->whereNotNull('orders.customer_id'),
            )
            ->when($managerId !== null, fn ($q) => $q->where('orders.manager_id', $managerId));

        if (! Schema::hasColumn('orders', 'customer_id')) {
            return ['rows' => [], 'total_revenue' => 0.0, 'total_orders' => 0];
        }

        $nameSql = $this->contractorDisplayNameSql('c');

        $query->leftJoin('contractors as c', 'c.id', '=', 'orders.customer_id')
            ->select([
                'orders.customer_id',
                DB::raw('MAX('.$nameSql.') as customer_name'),
                DB::raw('SUM(COALESCE(orders.customer_rate, 0)) as revenue'),
                DB::raw('COUNT(*) as orders_count'),
            ])
            ->groupBy('orders.customer_id');

        $raw = $query->get();

        $rows = $raw->map(function (object $row): array {
            return [
                'customer_id' => (int) $row->customer_id,
                'customer_name' => (string) ($row->customer_name ?? '—'),
                'revenue' => round((float) $row->revenue, 2),
                'orders_count' => (int) $row->orders_count,
            ];
        })->sortByDesc('revenue')->values();

        $totalRevenue = (float) $rows->sum('revenue');
        $totalOrders = (int) $rows->sum('orders_count');

        if ($totalRevenue <= 0) {
            return [
                'rows' => $rows->map(fn (array $r): array => [
                    ...$r,
                    'share_percent' => 0.0,
                    'cumulative_share_percent' => 0.0,
                    'abc_class' => 'C',
                ])->all(),
                'total_revenue' => 0.0,
                'total_orders' => $totalOrders,
            ];
        }

        $cumulative = 0.0;
        $classified = $rows->map(function (array $row) use ($totalRevenue, &$cumulative): array {
            $share = ($row['revenue'] / $totalRevenue) * 100;
            $cumulative += $share;
            $cum = round($cumulative, 2);
            $shareRounded = round($share, 2);
            $abc = $cum <= 80.0 ? 'A' : ($cum <= 95.0 ? 'B' : 'C');

            return [
                ...$row,
                'share_percent' => $shareRounded,
                'cumulative_share_percent' => $cum,
                'abc_class' => $abc,
            ];
        })->values()->all();

        return [
            'rows' => $classified,
            'total_revenue' => round($totalRevenue, 2),
            'total_orders' => $totalOrders,
        ];
    }

    /**
     * XYZ по коэффициенту вариации помесячной выручки клиента (несколько месяцев до конца периода).
     *
     * @return array{
     *     rows: list<array{
     *         customer_id: int,
     *         customer_name: string,
     *         monthly_revenues: list<float>,
     *         mean: float,
     *         std_dev: float,
     *         cv: float|null,
     *         xyz_class: 'X'|'Y'|'Z'|'-'
     *     }>,
     *     months: list<string>
     * }
     */
    public function xyzByCustomer(Carbon $from, Carbon $to, ?int $managerId, int $monthSpan = 6): array
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'customer_rate') || ! Schema::hasColumn('orders', 'customer_id')) {
            return ['rows' => [], 'months' => []];
        }

        $end = $to->copy()->endOfMonth();
        $start = $end->copy()->subMonths(max(1, $monthSpan) - 1)->startOfMonth();

        $months = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $months[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        $nameSql = $this->contractorDisplayNameSql('c');

        $cells = DB::table('orders')
            ->leftJoin('contractors as c', 'c.id', '=', 'orders.customer_id')
            ->whereNotNull('orders.customer_id')
            ->whereBetween('orders.order_date', [$start->toDateString(), $end->toDateString()])
            ->when(
                Schema::hasColumn('orders', 'deleted_at'),
                fn ($q) => $q->whereNull('orders.deleted_at'),
            )
            ->when($managerId !== null, fn ($q) => $q->where('orders.manager_id', $managerId))
            ->when(
                DB::getDriverName() === 'sqlite',
                fn ($q) => $q
                    ->select([
                        'orders.customer_id',
                        DB::raw($nameSql.' as customer_name'),
                        DB::raw("strftime('%Y-%m', orders.order_date) as ym"),
                        DB::raw('SUM(COALESCE(orders.customer_rate, 0)) as revenue'),
                    ])
                    ->groupBy('orders.customer_id', DB::raw("strftime('%Y-%m', orders.order_date)")),
                fn ($q) => $q
                    ->select([
                        'orders.customer_id',
                        DB::raw($nameSql.' as customer_name'),
                        DB::raw("DATE_FORMAT(orders.order_date, '%Y-%m') as ym"),
                        DB::raw('SUM(COALESCE(orders.customer_rate, 0)) as revenue'),
                    ])
                    ->groupBy('orders.customer_id', DB::raw("DATE_FORMAT(orders.order_date, '%Y-%m')")),
            )
            ->get();

        $byCustomer = $cells->groupBy('customer_id');

        $rows = $byCustomer->map(function (Collection $group) use ($months): array {
            /** @var object $first */
            $first = $group->first();
            $byMonth = $group->keyBy('ym');
            $series = [];
            foreach ($months as $m) {
                $series[] = round((float) ($byMonth->get($m)->revenue ?? 0), 2);
            }

            $mean = count($series) > 0 ? array_sum($series) / count($series) : 0.0;
            $std = $this->populationStdDev($series);
            $cv = $mean > 0.0001 ? $std / $mean : null;
            $xyz = $cv === null ? '-' : ($cv < 0.25 ? 'X' : ($cv < 0.75 ? 'Y' : 'Z'));

            return [
                'customer_id' => (int) $first->customer_id,
                'customer_name' => (string) ($first->customer_name ?? '—'),
                'monthly_revenues' => $series,
                'mean' => round($mean, 2),
                'std_dev' => round($std, 2),
                'cv' => $cv === null ? null : round($cv, 4),
                'xyz_class' => $xyz,
            ];
        })->sortByDesc('mean')->values()->all();

        return [
            'rows' => $rows,
            'months' => $months,
        ];
    }

    /**
     * Маржа, число закрытых заказов и средний чек (выручка / кол-во) по менеджерам.
     *
     * @return list<array{manager_id: int, manager_name: string, orders_count: int, margin: float, avg_check: float}>
     */
    public function managerStatsByCompletedOrders(Carbon $from, Carbon $to, ?int $managerId): array
    {
        return $this->completedOrderFinancialAnalytics->statsByManagers($from, $to, $managerId);
    }

    /**
     * @param  list<float>  $values
     */
    private function populationStdDev(array $values): float
    {
        $n = count($values);
        if ($n === 0) {
            return 0.0;
        }

        $mean = array_sum($values) / $n;
        $variance = 0.0;
        foreach ($values as $v) {
            $variance += ($v - $mean) ** 2;
        }

        return sqrt($variance / $n);
    }

    private function contractorDisplayNameSql(string $alias): string
    {
        if (! Schema::hasTable('contractors')) {
            return "''";
        }

        if (Schema::hasColumn('contractors', 'full_name')) {
            return "COALESCE(NULLIF(TRIM({$alias}.name), ''), {$alias}.full_name)";
        }

        return "COALESCE({$alias}.name, '')";
    }
}
