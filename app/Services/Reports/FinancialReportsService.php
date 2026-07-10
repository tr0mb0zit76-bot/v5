<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Services\CompletedOrderFinancialAnalytics;
use App\Support\OrderViewAuthorization;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
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
    public function abcByCustomer(Carbon $from, Carbon $to, ?User $user, string $party = 'customer'): array
    {
        return $this->abcByContractorParty($from, $to, $user, $party);
    }

    /**
     * @return array{
     *     rows: list<array{
     *         contractor_id: int,
     *         contractor_name: string,
     *         revenue: float,
     *         orders_count: int,
     *         share_percent: float,
     *         cumulative_share_percent: float,
     *         abc_class: 'A'|'B'|'C'
     *     }>,
     *     total_revenue: float,
     *     total_orders: int,
     *     party: string
     * }
     */
    public function abcByContractorParty(Carbon $from, Carbon $to, ?User $user, string $party = 'customer'): array
    {
        $party = $party === 'carrier' ? 'carrier' : 'customer';
        $contractorColumn = $party === 'carrier' ? 'carrier_id' : 'customer_id';
        $amountColumn = $party === 'carrier' ? 'carrier_rate' : 'customer_rate';

        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', $amountColumn) || ! Schema::hasColumn('orders', $contractorColumn)) {
            return ['rows' => [], 'total_revenue' => 0.0, 'total_orders' => 0, 'party' => $party];
        }

        $query = DB::table('orders')
            ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()])
            ->when(
                Schema::hasColumn('orders', 'deleted_at'),
                fn ($q) => $q->whereNull('orders.deleted_at'),
            )
            ->whereNotNull('orders.'.$contractorColumn);

        if ($user !== null) {
            OrderViewAuthorization::applyOrdersVisibilityScopeToQuery($query, $user, 'orders');
        }

        $nameSql = $this->contractorDisplayNameSql('c');

        $query->leftJoin('contractors as c', 'c.id', '=', 'orders.'.$contractorColumn);
        $this->applyContractorPartyFilter($query, 'c', $party);

        $query
            ->select([
                'orders.'.$contractorColumn.' as contractor_id',
                DB::raw('MAX('.$nameSql.') as contractor_name'),
                DB::raw('SUM(COALESCE(orders.'.$amountColumn.', 0)) as revenue'),
                DB::raw('COUNT(*) as orders_count'),
            ])
            ->groupBy('orders.'.$contractorColumn);

        $raw = $query->get();

        $rows = $raw->map(function (object $row): array {
            return [
                'contractor_id' => (int) $row->contractor_id,
                'contractor_name' => (string) ($row->contractor_name ?? '—'),
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
                'party' => $party,
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
            'party' => $party,
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
    public function xyzByCustomer(Carbon $from, Carbon $to, ?User $user, int $monthSpan = 6, string $party = 'customer'): array
    {
        return $this->xyzByContractorParty($from, $to, $user, $monthSpan, $party);
    }

    /**
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     months: list<string>,
     *     party: string
     * }
     */
    public function xyzByContractorParty(Carbon $from, Carbon $to, ?User $user, int $monthSpan = 6, string $party = 'customer'): array
    {
        $party = $party === 'carrier' ? 'carrier' : 'customer';
        $contractorColumn = $party === 'carrier' ? 'carrier_id' : 'customer_id';
        $amountColumn = $party === 'carrier' ? 'carrier_rate' : 'customer_rate';

        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', $amountColumn) || ! Schema::hasColumn('orders', $contractorColumn)) {
            return ['rows' => [], 'months' => [], 'party' => $party];
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

        $cellsQuery = DB::table('orders')
            ->leftJoin('contractors as c', 'c.id', '=', 'orders.'.$contractorColumn)
            ->whereNotNull('orders.'.$contractorColumn)
            ->whereBetween('orders.order_date', [$start->toDateString(), $end->toDateString()])
            ->when(
                Schema::hasColumn('orders', 'deleted_at'),
                fn ($q) => $q->whereNull('orders.deleted_at'),
            );

        if ($user !== null) {
            OrderViewAuthorization::applyOrdersVisibilityScopeToQuery($cellsQuery, $user, 'orders');
        }

        $this->applyContractorPartyFilter($cellsQuery, 'c', $party);

        $cells = $cellsQuery
            ->when(
                DB::getDriverName() === 'sqlite',
                fn ($q) => $q
                    ->select([
                        'orders.'.$contractorColumn.' as contractor_id',
                        DB::raw($nameSql.' as contractor_name'),
                        DB::raw("strftime('%Y-%m', orders.order_date) as ym"),
                        DB::raw('SUM(COALESCE(orders.'.$amountColumn.', 0)) as revenue'),
                    ])
                    ->groupBy('orders.'.$contractorColumn, DB::raw("strftime('%Y-%m', orders.order_date)")),
                fn ($q) => $q
                    ->select([
                        'orders.'.$contractorColumn.' as contractor_id',
                        DB::raw($nameSql.' as contractor_name'),
                        DB::raw("DATE_FORMAT(orders.order_date, '%Y-%m') as ym"),
                        DB::raw('SUM(COALESCE(orders.'.$amountColumn.', 0)) as revenue'),
                    ])
                    ->groupBy('orders.'.$contractorColumn, DB::raw("DATE_FORMAT(orders.order_date, '%Y-%m')")),
            )
            ->get();

        $byContractor = $cells->groupBy('contractor_id');

        $rows = $byContractor->map(function (Collection $group) use ($months): array {
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
                'contractor_id' => (int) $first->contractor_id,
                'contractor_name' => (string) ($first->contractor_name ?? '—'),
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
            'party' => $party,
        ];
    }

    /**
     * Маржа, число закрытых заказов и средний чек (выручка / кол-во) по менеджерам.
     *
     * @return list<array{manager_id: int, manager_name: string, orders_count: int, margin: float, avg_check: float}>
     */
    public function managerStatsByCompletedOrders(Carbon $from, Carbon $to, ?User $user): array
    {
        return $this->completedOrderFinancialAnalytics->statsByManagers($from, $to, $user);
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

    private function applyContractorPartyFilter(Builder $query, string $alias, string $party): void
    {
        if (! Schema::hasTable('contractors') || ! Schema::hasColumn('contractors', 'type')) {
            return;
        }

        if ($party === 'carrier') {
            $query->whereIn("{$alias}.type", ['carrier', 'both']);
        } else {
            $query->whereIn("{$alias}.type", ['customer', 'both']);
        }
    }
}
