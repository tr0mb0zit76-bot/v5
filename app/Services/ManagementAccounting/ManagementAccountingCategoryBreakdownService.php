<?php

namespace App\Services\ManagementAccounting;

use App\Models\FleetTrip;
use App\Models\ManagementExpenseCategory;
use App\Models\ManagementPayrollHalfUser;
use App\Models\ManagementStatementLine;
use App\Models\Order;
use App\Models\PaymentSchedulePaymentEvent;
use App\Support\ManagementCostCategoryCodes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ManagementAccountingCategoryBreakdownService
{
    /**
     * @return array{label: string, items: list<array{id: int, name: string, actual_out: float, actual_in: float}>}
     */
    public function forCategory(
        ManagementExpenseCategory $category,
        Collection $categories,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        if ($this->isPayrollCategory($category)) {
            return [
                'label' => 'employee',
                'items' => $this->payrollByEmployee($start, $end),
            ];
        }

        if ($this->isCostCategory($category)) {
            $categoryIds = $this->collectDescendantIds($categories, $category->id);

            return [
                'label' => 'order',
                'items' => $this->costByOrder($start, $end, $categoryIds),
            ];
        }

        return [
            'label' => 'none',
            'items' => [],
        ];
    }

    private function isPayrollCategory(ManagementExpenseCategory $category): bool
    {
        return $category->code === 'group_payroll'
            || in_array($category->kind, ['payroll_accrued', 'payroll_paid', 'payroll_other'], true);
    }

    private function isCostCategory(ManagementExpenseCategory $category): bool
    {
        return in_array($category->code, ['group_cost', ...ManagementCostCategoryCodes::costLeafCodes()], true)
            || in_array($category->kind, ['operational_out', 'operational_out_hired', 'operational_out_own_fleet'], true);
    }

    /**
     * @return list<array{id: int, name: string, actual_out: float, actual_in: float}>
     */
    private function payrollByEmployee(CarbonImmutable $start, CarbonImmutable $end): array
    {
        if (! Schema::hasTable('management_payroll_half_users')) {
            return [];
        }

        return ManagementPayrollHalfUser::query()
            ->whereHas('payrollHalf', function ($query) use ($start, $end): void {
                $query->whereDate('period_start', '<=', $end->toDateString())
                    ->whereDate('period_end', '>=', $start->toDateString());
            })
            ->with('user:id,name')
            ->get()
            ->groupBy('user_id')
            ->map(function (Collection $rows): array {
                $user = $rows->first()?->user;

                return [
                    'id' => (int) ($user?->id ?? 0),
                    'name' => (string) ($user?->name ?? '—'),
                    'actual_out' => round((float) $rows->sum('paid_amount'), 2),
                    'actual_in' => round((float) $rows->sum('accrued_amount'), 2),
                ];
            })
            ->sortByDesc('actual_out')
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $categoryIds
     * @return list<array{id: int, name: string, actual_out: float, actual_in: float}>
     */
    private function costByOrder(CarbonImmutable $start, CarbonImmutable $end, array $categoryIds): array
    {
        $totals = [];

        if (Schema::hasTable('management_statement_lines') && $categoryIds !== []) {
            ManagementStatementLine::query()
                ->where('status', 'allocated')
                ->where('direction', 'out')
                ->whereIn('allocation_category_id', $categoryIds)
                ->whereNotNull('allocation_order_id')
                ->whereBetween('operation_date', [$start->toDateString(), $end->toDateString()])
                ->get(['allocation_order_id', 'amount'])
                ->each(function (ManagementStatementLine $line) use (&$totals): void {
                    $orderId = (int) $line->allocation_order_id;
                    $totals[$orderId] = ($totals[$orderId] ?? 0.0) + (float) $line->amount;
                });
        }

        $costLeafIds = ManagementExpenseCategory::query()
            ->whereIn('code', ManagementCostCategoryCodes::costLeafCodes())
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($costLeafIds !== []
            && array_intersect($categoryIds, $costLeafIds) !== []
            && Schema::hasTable('payment_schedule_payment_events')) {
            PaymentSchedulePaymentEvent::query()
                ->active()
                ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
                ->whereIn('party', ['carrier', 'contractor'])
                ->whereNotNull('order_id')
                ->where(function ($query): void {
                    $query->whereNull('transaction_reference')
                        ->orWhere('transaction_reference', 'not like', 'mgmt:%');
                })
                ->get(['order_id', 'amount'])
                ->each(function (PaymentSchedulePaymentEvent $event) use (&$totals): void {
                    $orderId = (int) $event->order_id;
                    $totals[$orderId] = ($totals[$orderId] ?? 0.0) + (float) $event->amount;
                });
        }

        $ownFleetCategoryId = ManagementExpenseCategory::query()
            ->where('code', ManagementCostCategoryCodes::OWN_FLEET)
            ->value('id');

        if ($ownFleetCategoryId !== null
            && in_array((int) $ownFleetCategoryId, $categoryIds, true)
            && Schema::hasTable('fleet_trips')) {
            FleetTrip::query()
                ->where('status', 'completed')
                ->where('total_cost', '>', 0)
                ->whereNotNull('order_id')
                ->where(function ($query) use ($start, $end): void {
                    $query->whereBetween('completed_at', [$start->startOfDay(), $end->endOfDay()])
                        ->orWhere(function ($fallback) use ($start, $end): void {
                            $fallback->whereNull('completed_at')
                                ->whereBetween('started_at', [$start->startOfDay(), $end->endOfDay()]);
                        });
                })
                ->get(['order_id', 'total_cost'])
                ->each(function (FleetTrip $trip) use (&$totals): void {
                    $orderId = (int) $trip->order_id;
                    $totals[$orderId] = ($totals[$orderId] ?? 0.0) + (float) $trip->total_cost;
                });
        }

        if ($totals === []) {
            return [];
        }

        $orderLabels = Order::query()
            ->whereIn('id', array_keys($totals))
            ->get(['id', 'order_number'])
            ->keyBy('id');

        return collect($totals)
            ->map(function (float $amount, int $orderId) use ($orderLabels): array {
                $order = $orderLabels->get($orderId);

                return [
                    'id' => $orderId,
                    'name' => $order?->order_number ? 'Заказ '.$order->order_number : 'Заказ #'.$orderId,
                    'actual_out' => round($amount, 2),
                    'actual_in' => 0.0,
                ];
            })
            ->sortByDesc('actual_out')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ManagementExpenseCategory>  $categories
     * @return list<int>
     */
    private function collectDescendantIds(Collection $categories, int $rootId): array
    {
        $ids = [$rootId];

        foreach ($categories->where('parent_id', $rootId) as $child) {
            $ids = [...$ids, ...$this->collectDescendantIds($categories, $child->id)];
        }

        return array_values(array_unique($ids));
    }
}
