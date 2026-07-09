<?php

namespace App\Services\Budgeting;

use App\Models\BudgetSalesTarget;
use App\Services\CompletedOrderFinancialAnalytics;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BudgetSalesPerformanceService
{
    public function __construct(
        private readonly CompletedOrderFinancialAnalytics $completedOrderFinancialAnalytics,
    ) {}

    /**
     * @param  list<int>  $userIds
     * @return array<int, array<string, float>>
     */
    public function actualsForMonth(CarbonImmutable $periodMonth, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $start = $periodMonth->startOfMonth();
        $end = $periodMonth->endOfMonth();

        $actuals = [];

        foreach ($userIds as $userId) {
            $actuals[$userId] = [
                BudgetSalesTarget::METRIC_REVENUE => 0.0,
                BudgetSalesTarget::METRIC_MARGIN => 0.0,
                BudgetSalesTarget::METRIC_LEADS => 0.0,
                BudgetSalesTarget::METRIC_ORDERS => 0.0,
            ];
        }

        $this->mergeOrderActuals($actuals, $start, $end, $userIds);
        $this->mergeLeadActuals($actuals, $start, $end, $userIds);

        return $actuals;
    }

    /**
     * @param  array<int, array<string, float>>  $actuals
     * @param  list<int>  $userIds
     */
    private function mergeOrderActuals(array &$actuals, CarbonImmutable $start, CarbonImmutable $end, array $userIds): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'status')) {
            return;
        }

        $ownerColumn = $this->orderOwnerColumn();

        if ($ownerColumn === null) {
            return;
        }

        $dateCol = $this->completedOrderFinancialAnalytics->completionDateSql();
        $fromDate = $start->toDateString();
        $toDate = $end->toDateString();

        $query = DB::table('orders')
            ->whereIn($ownerColumn, $userIds)
            ->whereIn('orders.status', ['closed', 'completed']);
        $query->whereRaw("{$dateCol} between ? and ?", [$fromDate, $toDate]);

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $query->whereNull('orders.deleted_at');
        }

        $revenueExpr = Schema::hasColumn('orders', 'customer_rate')
            ? 'SUM(COALESCE(orders.customer_rate, 0))'
            : 'SUM(0)';

        $marginExpr = Schema::hasColumn('orders', 'delta')
            ? 'SUM(COALESCE(orders.delta, 0))'
            : 'SUM(0)';

        $rows = $query
            ->select([
                DB::raw("{$ownerColumn} as user_id"),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw("{$revenueExpr} as revenue"),
                DB::raw("{$marginExpr} as margin"),
            ])
            ->groupBy(DB::raw($ownerColumn))
            ->get();

        foreach ($rows as $row) {
            $userId = (int) $row->user_id;

            if (! isset($actuals[$userId])) {
                continue;
            }

            $actuals[$userId][BudgetSalesTarget::METRIC_REVENUE] = round((float) $row->revenue, 2);
            $actuals[$userId][BudgetSalesTarget::METRIC_MARGIN] = round((float) $row->margin, 2);
            $actuals[$userId][BudgetSalesTarget::METRIC_ORDERS] = (float) $row->orders_count;
        }
    }

    /**
     * @param  array<int, array<string, float>>  $actuals
     * @param  list<int>  $userIds
     */
    private function mergeLeadActuals(array &$actuals, CarbonImmutable $start, CarbonImmutable $end, array $userIds): void
    {
        if (! Schema::hasTable('leads') || ! Schema::hasColumn('leads', 'responsible_id')) {
            return;
        }

        $query = DB::table('leads')
            ->whereIn('responsible_id', $userIds)
            ->where('status', 'won')
            ->whereBetween('updated_at', [
                $start->startOfDay()->toDateTimeString(),
                $end->endOfDay()->toDateTimeString(),
            ]);

        if (Schema::hasColumn('leads', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $rows = $query
            ->select([
                'responsible_id as user_id',
                DB::raw('COUNT(*) as leads_count'),
            ])
            ->groupBy('responsible_id')
            ->get();

        foreach ($rows as $row) {
            $userId = (int) $row->user_id;

            if (! isset($actuals[$userId])) {
                continue;
            }

            $actuals[$userId][BudgetSalesTarget::METRIC_LEADS] = (float) $row->leads_count;
        }
    }

    private function orderOwnerColumn(): ?string
    {
        if (Schema::hasColumn('orders', 'order_owner_id')) {
            return 'orders.order_owner_id';
        }

        if (Schema::hasColumn('orders', 'manager_id')) {
            return 'orders.manager_id';
        }

        return null;
    }
}
