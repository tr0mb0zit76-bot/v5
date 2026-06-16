<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Services\Disposition\DispositionInProgressOrderScope;
use App\Support\EndToEndOrderPipelineColumn;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class PipelineKpiService
{
    public function __construct(
        private readonly DispositionInProgressOrderScope $dispositionScope,
        private readonly EndToEndPipelineSnapshot $snapshot,
    ) {}

    /**
     * @return array{
     *     period_months: int,
     *     linked_leads_count: int,
     *     avg_lead_to_order_days: ?float,
     *     closed_with_lead_count: int,
     *     avg_lead_to_closed_days: ?float,
     *     active_orders_count: int,
     *     orders_with_overdue_payments: int,
     *     overdue_payments_percent: float
     * }
     */
    public function metricsForUser(User $user, int $periodMonths = 12): array
    {
        $orders = $this->ordersForMetrics($user, $periodMonths);

        if ($orders->isEmpty()) {
            return $this->emptyMetrics($periodMonths);
        }

        $withLead = $orders->filter(
            fn (Order $order): bool => $order->lead_id !== null && $order->lead !== null,
        );

        $leadToOrderDays = $withLead
            ->map(function (Order $order): float {
                $leadCreated = $order->lead?->created_at;
                $orderCreated = $order->created_at;

                if ($leadCreated === null || $orderCreated === null) {
                    return -1;
                }

                return (float) $leadCreated->diffInDays($orderCreated);
            })
            ->filter(fn (float $days): bool => $days >= 0)
            ->values();

        $closedWithLead = $withLead->filter(function (Order $order): bool {
            $column = $this->snapshot->orderColumn($order);

            return in_array($column, [
                EndToEndOrderPipelineColumn::Closed,
                EndToEndOrderPipelineColumn::AccountingHandoff,
            ], true);
        });

        $leadToClosedDays = $closedWithLead
            ->map(function (Order $order): float {
                $leadCreated = $order->lead?->created_at;

                if ($leadCreated === null) {
                    return -1;
                }

                $closedAt = $order->accounting_handoff_at ?? $order->updated_at;

                return (float) $leadCreated->diffInDays($closedAt);
            })
            ->filter(fn (float $days): bool => $days >= 0)
            ->values();

        $activeOrders = $orders->reject(function (Order $order): bool {
            $column = $this->snapshot->orderColumn($order);

            return in_array($column, [
                EndToEndOrderPipelineColumn::Closed,
                EndToEndOrderPipelineColumn::AccountingHandoff,
                EndToEndOrderPipelineColumn::Disruption,
            ], true);
        });

        $overdueOrderIds = $this->overdueOrderIdsFor($activeOrders->pluck('id')->all());
        $ordersWithOverdue = $activeOrders
            ->filter(fn (Order $order): bool => in_array($order->id, $overdueOrderIds, true))
            ->count();

        $activeCount = $activeOrders->count();

        return [
            'period_months' => $periodMonths,
            'linked_leads_count' => $withLead->count(),
            'avg_lead_to_order_days' => $this->averageDays($leadToOrderDays),
            'closed_with_lead_count' => $closedWithLead->count(),
            'avg_lead_to_closed_days' => $this->averageDays($leadToClosedDays),
            'active_orders_count' => $activeCount,
            'orders_with_overdue_payments' => $ordersWithOverdue,
            'overdue_payments_percent' => $activeCount > 0
                ? round(($ordersWithOverdue / $activeCount) * 100, 1)
                : 0.0,
        ];
    }

    /**
     * @param  list<int>  $orderIds
     * @return list<int>
     */
    public function overdueOrderIdsFor(array $orderIds): array
    {
        if ($orderIds === [] || ! Schema::hasTable('payment_schedules')) {
            return [];
        }

        return PaymentSchedule::query()
            ->whereIn('order_id', $orderIds)
            ->where('status', 'overdue')
            ->distinct()
            ->pluck('order_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @return array{
     *     period_months: int,
     *     linked_leads_count: int,
     *     avg_lead_to_order_days: ?float,
     *     closed_with_lead_count: int,
     *     avg_lead_to_closed_days: ?float,
     *     active_orders_count: int,
     *     orders_with_overdue_payments: int,
     *     overdue_payments_percent: float
     * }
     */
    private function emptyMetrics(int $periodMonths): array
    {
        return [
            'period_months' => $periodMonths,
            'linked_leads_count' => 0,
            'avg_lead_to_order_days' => null,
            'closed_with_lead_count' => 0,
            'avg_lead_to_closed_days' => null,
            'active_orders_count' => 0,
            'orders_with_overdue_payments' => 0,
            'overdue_payments_percent' => 0.0,
        ];
    }

    /**
     * @return Collection<int, Order>
     */
    private function ordersForMetrics(User $user, int $periodMonths): Collection
    {
        $builder = Order::query()
            ->with([
                'lead:id,created_at',
                'legs' => fn ($query) => $query->orderBy('sequence'),
                'legs.routePoints' => fn ($query) => $query->orderBy('sequence'),
            ])
            ->orderByDesc('id');

        $this->dispositionScope->applyVisibilityForArea($builder, $user, 'pipeline');

        if (Schema::hasColumn('orders', 'created_at') && $periodMonths > 0) {
            $builder->where('created_at', '>=', now()->subMonths($periodMonths));
        }

        return $builder->get();
    }

    /**
     * @param  Collection<int, float>  $days
     */
    private function averageDays(Collection $days): ?float
    {
        if ($days->isEmpty()) {
            return null;
        }

        return round($days->avg(), 1);
    }
}
