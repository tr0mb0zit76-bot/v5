<?php

namespace App\Services;

use App\Models\User;
use App\Support\OrderViewAuthorization;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Фактические доход / расход / маржа по заказам в статусах «закрыт» / legacy «completed».
 * Дата попадания в период — дата закрытия (status_updated_at) или, если её нет, дата заказа.
 */
class CompletedOrderFinancialAnalytics
{
    private const COMPLETED_STATUSES = ['closed', 'completed'];

    public function completionDateSql(): string
    {
        $driver = DB::getDriverName();

        if (Schema::hasColumn('orders', 'status_updated_at')) {
            return $driver === 'sqlite'
                ? 'date(COALESCE(orders.status_updated_at, orders.order_date))'
                : 'COALESCE(DATE(orders.status_updated_at), orders.order_date)';
        }

        return $driver === 'sqlite'
            ? 'date(orders.order_date)'
            : 'DATE(orders.order_date)';
    }

    public function monthBucketSql(): string
    {
        $driver = DB::getDriverName();

        if (Schema::hasColumn('orders', 'status_updated_at')) {
            return $driver === 'sqlite'
                ? "strftime('%Y-%m', COALESCE(orders.status_updated_at, orders.order_date))"
                : "DATE_FORMAT(COALESCE(orders.status_updated_at, orders.order_date), '%Y-%m')";
        }

        return $driver === 'sqlite'
            ? "strftime('%Y-%m', orders.order_date)"
            : "DATE_FORMAT(orders.order_date, '%Y-%m')";
    }

    /**
     * Помесячные суммы для графика на дашборде (один менеджер).
     *
     * @return list<array{ym: string, label: string, income: float, expense: float, margin: float}>
     */
    public function monthlyBucketsForManager(int $managerId, Carbon $from, Carbon $to): array
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'manager_id')) {
            return [];
        }

        if (! Schema::hasColumn('orders', 'status') || ! Schema::hasColumn('orders', 'order_date')) {
            return [];
        }

        $fromDate = $from->copy()->startOfDay()->toDateString();
        $toDate = $to->copy()->endOfDay()->toDateString();

        $dateCol = $this->completionDateSql();
        $monthExpr = $this->monthBucketSql();

        $query = DB::table('orders')
            ->where('orders.manager_id', $managerId);
        $this->applyCompletedOrderScope($query);
        $query->whereRaw("{$dateCol} between ? and ?", [$fromDate, $toDate]);

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $query->whereNull('orders.deleted_at');
        }

        $incomeExpr = Schema::hasColumn('orders', 'customer_rate')
            ? 'SUM(COALESCE(orders.customer_rate, 0))'
            : 'SUM(0)';

        $expenseInner = $this->expensePerRowSql();
        $expenseExpr = $expenseInner === '0' ? 'SUM(0)' : "SUM({$expenseInner})";

        $marginExpr = Schema::hasColumn('orders', 'delta')
            ? 'SUM(COALESCE(orders.delta, 0))'
            : 'SUM(0)';

        $rows = $query
            ->select([
                DB::raw("{$monthExpr} as ym"),
                DB::raw("{$incomeExpr} as income"),
                DB::raw("{$expenseExpr} as expense"),
                DB::raw("{$marginExpr} as margin"),
            ])
            ->groupBy(DB::raw($monthExpr))
            ->get()
            ->keyBy('ym');

        $out = [];
        $cursor = $from->copy()->startOfMonth();
        $end = $to->copy()->endOfMonth();

        while ($cursor->lte($end)) {
            $ym = $cursor->format('Y-m');
            $row = $rows->get($ym);
            $out[] = [
                'ym' => $ym,
                'label' => $cursor->copy()->locale('ru')->translatedFormat('M Y'),
                'income' => round((float) ($row->income ?? 0), 2),
                'expense' => round((float) ($row->expense ?? 0), 2),
                'margin' => round((float) ($row->margin ?? 0), 2),
            ];
            $cursor->addMonth();
        }

        return $out;
    }

    /**
     * Помесячные суммы по всем закрытым заказам (без фильтра по менеджеру) — для дашборда руководителя / админа / бухгалтера.
     *
     * @return list<array{ym: string, label: string, income: float, expense: float, margin: float}>
     */
    public function monthlyBucketsAggregate(Carbon $from, Carbon $to): array
    {
        if (! Schema::hasTable('orders')) {
            return [];
        }

        if (! Schema::hasColumn('orders', 'status') || ! Schema::hasColumn('orders', 'order_date')) {
            return [];
        }

        $fromDate = $from->copy()->startOfDay()->toDateString();
        $toDate = $to->copy()->endOfDay()->toDateString();

        $dateCol = $this->completionDateSql();
        $monthExpr = $this->monthBucketSql();

        $query = DB::table('orders');
        $this->applyCompletedOrderScope($query);
        $query->whereRaw("{$dateCol} between ? and ?", [$fromDate, $toDate]);

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $query->whereNull('orders.deleted_at');
        }

        $incomeExpr = Schema::hasColumn('orders', 'customer_rate')
            ? 'SUM(COALESCE(orders.customer_rate, 0))'
            : 'SUM(0)';

        $expenseInner = $this->expensePerRowSql();
        $expenseExpr = $expenseInner === '0' ? 'SUM(0)' : "SUM({$expenseInner})";

        $marginExpr = Schema::hasColumn('orders', 'delta')
            ? 'SUM(COALESCE(orders.delta, 0))'
            : 'SUM(0)';

        $rows = $query
            ->select([
                DB::raw("{$monthExpr} as ym"),
                DB::raw("{$incomeExpr} as income"),
                DB::raw("{$expenseExpr} as expense"),
                DB::raw("{$marginExpr} as margin"),
            ])
            ->groupBy(DB::raw($monthExpr))
            ->get()
            ->keyBy('ym');

        $out = [];
        $cursor = $from->copy()->startOfMonth();
        $end = $to->copy()->endOfMonth();

        while ($cursor->lte($end)) {
            $ym = $cursor->format('Y-m');
            $row = $rows->get($ym);
            $out[] = [
                'ym' => $ym,
                'label' => $cursor->copy()->locale('ru')->translatedFormat('M Y'),
                'income' => round((float) ($row->income ?? 0), 2),
                'expense' => round((float) ($row->expense ?? 0), 2),
                'margin' => round((float) ($row->margin ?? 0), 2),
            ];
            $cursor->addMonth();
        }

        return $out;
    }

    /**
     * Сводка по менеджерам за период (для отчётов).
     *
     * @return list<array{manager_id: int, manager_name: string, orders_count: int, margin: float, avg_check: float}>
     */
    public function statsByManagers(Carbon $from, Carbon $to, ?User $user): array
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'manager_id')) {
            return [];
        }

        if (! Schema::hasColumn('orders', 'status') || ! Schema::hasColumn('orders', 'order_date')) {
            return [];
        }

        $fromDate = $from->copy()->startOfDay()->toDateString();
        $toDate = $to->copy()->endOfDay()->toDateString();

        $dateCol = $this->completionDateSql();

        $query = DB::table('orders')
            ->whereNotNull('orders.manager_id');
        $this->applyCompletedOrderScope($query);
        $query->whereRaw("{$dateCol} between ? and ?", [$fromDate, $toDate]);

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $query->whereNull('orders.deleted_at');
        }

        if ($user !== null) {
            OrderViewAuthorization::applyOrdersVisibilityScopeToQuery($query, $user, 'orders');
        }

        $incomeExpr = Schema::hasColumn('orders', 'customer_rate')
            ? 'SUM(COALESCE(orders.customer_rate, 0))'
            : 'SUM(0)';

        $marginExpr = Schema::hasColumn('orders', 'delta')
            ? 'SUM(COALESCE(orders.delta, 0))'
            : 'SUM(0)';

        $query->leftJoin('users', 'users.id', '=', 'orders.manager_id')
            ->select([
                'orders.manager_id',
                DB::raw("MAX(COALESCE(NULLIF(TRIM(users.name), ''), '—')) as manager_name"),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw("{$marginExpr} as margin"),
                DB::raw("{$incomeExpr} as revenue"),
            ])
            ->groupBy('orders.manager_id');

        return $query->get()->map(function (object $row): array {
            $count = (int) $row->orders_count;
            $revenue = (float) ($row->revenue ?? 0);

            return [
                'manager_id' => (int) $row->manager_id,
                'manager_name' => (string) ($row->manager_name ?? '—'),
                'orders_count' => $count,
                'margin' => round((float) ($row->margin ?? 0), 2),
                'avg_check' => $count > 0 ? round($revenue / $count, 2) : 0.0,
            ];
        })->sortByDesc('margin')->values()->all();
    }

    private function expensePerRowSql(): string
    {
        $parts = [];
        if (Schema::hasColumn('orders', 'carrier_rate')) {
            $parts[] = 'COALESCE(orders.carrier_rate, 0)';
        }
        if (Schema::hasColumn('orders', 'additional_expenses')) {
            $parts[] = 'COALESCE(orders.additional_expenses, 0)';
        }

        if ($parts === []) {
            return '0';
        }

        return implode(' + ', $parts);
    }

    /**
     * @param  Builder  $query
     */
    private function applyCompletedOrderScope($query): void
    {
        if (Schema::hasColumn('orders', 'manual_status')) {
            $query->whereRaw("COALESCE(orders.manual_status, orders.status) IN ('closed', 'completed')");

            return;
        }

        $query->whereIn('orders.status', self::COMPLETED_STATUSES);
    }
}
