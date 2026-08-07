<?php

namespace App\Services\Contractor;

use App\Enums\OrderClaimStatus;
use App\Models\Contractor;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderClaim;
use App\Models\PaymentSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class ContractorCrmFactsCollector
{
    /**
     * @return array<string, mixed>
     */
    public function collect(Contractor $contractor, ?Carbon $since = null): array
    {
        $since ??= now()->subMonths(max(1, (int) config('contractor_enrichment.window_months', 24)));
        $ordersLimit = max(1, (int) config('contractor_enrichment.recent_orders_limit', 5));

        $customerOrdersQuery = $contractor->customerOrders()->where('created_at', '>=', $since);
        $carrierOrdersQuery = $contractor->carrierOrders()->where('created_at', '>=', $since);

        $customerCount = (clone $customerOrdersQuery)->count();
        $carrierCount = (clone $carrierOrdersQuery)->count();

        $firstCustomer = $contractor->customerOrders()->orderBy('created_at')->value('created_at');
        $firstCarrier = $contractor->carrierOrders()->orderBy('created_at')->value('created_at');
        $firstAt = collect([$firstCustomer, $firstCarrier])->filter()->min();

        $recentOrders = $this->recentOrders($contractor, $ordersLimit);

        $leadsOpen = 0;
        $leadsWon = 0;
        $leadsLost = 0;
        $lostPriceHints = 0;
        if (Schema::hasTable('leads')) {
            $leadsBase = $contractor->leadsAsCounterparty();
            $leadsOpen = (clone $leadsBase)->whereNotIn('status', ['won', 'lost'])->count();
            $leadsWon = (clone $leadsBase)->where('status', 'won')->count();
            $leadsLost = (clone $leadsBase)->where('status', 'lost')->count();

            if (Schema::hasColumn('leads', 'lost_reason')) {
                $lostPriceHints = Lead::query()
                    ->where('counterparty_id', $contractor->id)
                    ->where('status', 'lost')
                    ->where(function ($q): void {
                        $q->where('lost_reason', 'like', '%дорог%')
                            ->orWhere('lost_reason', 'like', '%цен%')
                            ->orWhere('lost_reason', 'like', '%ставк%');
                    })
                    ->count();
            }
        }

        $claimsOpen = 0;
        $claimsClosed = 0;
        if (Schema::hasTable('order_claims')) {
            $orderIds = Order::query()
                ->where(function ($q) use ($contractor): void {
                    $q->where('customer_id', $contractor->id)
                        ->orWhere('carrier_id', $contractor->id);
                })
                ->pluck('id');

            if ($orderIds->isNotEmpty()) {
                $openStatuses = [
                    OrderClaimStatus::Draft->value,
                    OrderClaimStatus::Open->value,
                    OrderClaimStatus::InReview->value,
                    OrderClaimStatus::Negotiating->value,
                ];
                $closedStatuses = [
                    OrderClaimStatus::Resolved->value,
                    OrderClaimStatus::Rejected->value,
                    OrderClaimStatus::WrittenOff->value,
                ];

                $claimsOpen = OrderClaim::query()
                    ->whereIn('order_id', $orderIds)
                    ->whereIn('status', $openStatuses)
                    ->count();
                $claimsClosed = OrderClaim::query()
                    ->whereIn('order_id', $orderIds)
                    ->whereIn('status', $closedStatuses)
                    ->count();
            }
        }

        $overdueCount = 0;
        $overdueAmount = 0.0;
        if (Schema::hasTable('payment_schedules') && Schema::hasColumn('payment_schedules', 'counterparty_id')) {
            $overdue = PaymentSchedule::query()
                ->where('counterparty_id', $contractor->id)
                ->where('remaining_amount', '>', 0)
                ->whereDate('planned_date', '<', now()->toDateString())
                ->whereNotIn('status', ['paid', 'cancelled']);

            $overdueCount = (clone $overdue)->count();
            $overdueAmount = (float) (clone $overdue)->sum('remaining_amount');
        }

        $lastInteraction = null;
        $objectionTags = [];
        if (Schema::hasTable('contractor_interactions')) {
            $last = $contractor->interactions()->orderByDesc('contacted_at')->first();
            if ($last !== null) {
                $lastInteraction = [
                    'contacted_at' => optional($last->contacted_at)?->toIso8601String(),
                    'channel' => $last->channel,
                    'outcome_code' => $last->outcome_code,
                    'summary' => $last->summary,
                ];
            }

            $objectionTags = $contractor->interactions()
                ->whereNotNull('objection_tags')
                ->orderByDesc('contacted_at')
                ->limit(20)
                ->get()
                ->pluck('objection_tags')
                ->filter(fn ($tags): bool => is_array($tags))
                ->flatten()
                ->map(fn ($tag): string => trim((string) $tag))
                ->filter()
                ->countBy()
                ->sortDesc()
                ->keys()
                ->take(5)
                ->values()
                ->all();
        }

        return [
            'identity' => [
                'name' => $contractor->name,
                'inn' => $contractor->inn,
                'type' => $contractor->type,
                'legal_form' => $contractor->legal_form,
                'full_name' => $contractor->full_name,
                'website' => $contractor->website,
                'short_description' => $contractor->short_description,
            ],
            'relationships' => [
                'customer_orders_count' => $customerCount,
                'carrier_orders_count' => $carrierCount,
                'first_order_at' => $firstAt ? Carbon::parse($firstAt)->toIso8601String() : null,
                'window_months' => (int) config('contractor_enrichment.window_months', 24),
            ],
            'money' => [
                'debt_limit' => $contractor->debt_limit,
                'overdue_schedules_count' => $overdueCount,
                'overdue_amount' => round($overdueAmount, 2),
                'default_customer_payment_form' => $contractor->default_customer_payment_form,
                'default_carrier_payment_form' => $contractor->default_carrier_payment_form,
            ],
            'funnel' => [
                'leads_open' => $leadsOpen,
                'leads_won' => $leadsWon,
                'leads_lost' => $leadsLost,
                'lost_price_hints' => $lostPriceHints,
            ],
            'claims' => [
                'open' => $claimsOpen,
                'closed' => $claimsClosed,
            ],
            'communications' => [
                'last_interaction' => $lastInteraction,
                'top_objection_tags' => $objectionTags,
            ],
            'recent_orders' => $recentOrders,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentOrders(Contractor $contractor, int $limit): array
    {
        $columns = ['id', 'order_number', 'status', 'created_at', 'customer_id', 'carrier_id'];
        if (Schema::hasColumn('orders', 'customer_rate')) {
            $columns[] = 'customer_rate';
        }
        if (Schema::hasColumn('orders', 'carrier_rate')) {
            $columns[] = 'carrier_rate';
        }

        $customer = $contractor->customerOrders()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get($columns);

        $carrier = $contractor->carrierOrders()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get($columns);

        return $customer->concat($carrier)
            ->sortByDesc(fn (Order $order) => $order->created_at?->timestamp ?? 0)
            ->take($limit)
            ->map(function (Order $order) use ($contractor): array {
                $asCustomer = (int) $order->customer_id === (int) $contractor->id;

                return [
                    'id' => $order->id,
                    'number' => $order->order_number,
                    'status' => $order->status,
                    'role' => $asCustomer ? 'customer' : 'carrier',
                    'amount' => $asCustomer
                        ? ($order->customer_rate ?? null)
                        : ($order->carrier_rate ?? null),
                    'created_at' => optional($order->created_at)?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }
}
