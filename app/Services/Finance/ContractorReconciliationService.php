<?php

namespace App\Services\Finance;

use App\Models\Contractor;
use App\Models\Order;
use App\Models\PaymentSchedulePaymentEvent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ContractorReconciliationService
{
    public function __construct(
        private readonly PaymentSchedulePaymentLedgerService $ledgerService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(
        int $contractorId,
        ?string $dateFrom,
        ?string $dateTo,
        ?int $userId,
        ?string $roleName,
        string $ordersScope,
    ): array {
        $contractor = Contractor::query()->findOrFail($contractorId);

        $from = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : null;
        $to = $dateTo ? Carbon::parse($dateTo)->endOfDay() : null;

        $asCustomer = $this->buildCustomerSection($contractorId, $from, $to, $userId, $roleName, $ordersScope);
        $asCarrier = $this->buildCarrierSection($contractorId, $from, $to, $userId, $roleName, $ordersScope);

        $contractorType = strtolower(trim((string) ($contractor->type ?? 'both')));

        return [
            'contractor' => [
                'id' => $contractor->id,
                'name' => $this->contractorDisplayName($contractor),
                'inn' => $contractor->inn,
                'type' => $contractorType !== '' ? $contractorType : 'both',
            ],
            'show_as_customer' => in_array($contractorType, ['customer', 'both'], true),
            'show_as_carrier' => in_array($contractorType, ['carrier', 'both'], true),
            'period' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
            'as_customer' => $asCustomer,
            'as_carrier' => $asCarrier,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCustomerSection(
        int $contractorId,
        ?Carbon $from,
        ?Carbon $to,
        ?int $userId,
        ?string $roleName,
        string $ordersScope,
    ): array {
        $orders = $this->ordersBaseQuery($userId, $roleName, $ordersScope)
            ->where('orders.customer_id', $contractorId)
            ->when($from, fn ($q) => $q->whereDate('orders.order_date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('orders.order_date', '<=', $to->toDateString()))
            ->orderByDesc('orders.order_date')
            ->orderByDesc('orders.id')
            ->get([
                'orders.id',
                'orders.order_number',
                'orders.order_date',
                'orders.customer_rate',
            ]);

        $orderIds = $orders->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $paidByOrder = $this->paidAmountsByOrder($orderIds, 'customer', $contractorId, $from, $to);

        $rows = $orders->map(function (Order $order) use ($paidByOrder): array {
            $accrued = $this->resolveCustomerAccrued($order);
            $paid = (float) ($paidByOrder[(int) $order->id] ?? 0);

            return [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'order_date' => optional($order->order_date)?->toDateString(),
                'accrued' => round($accrued, 2),
                'paid' => round($paid, 2),
                'balance' => round($accrued - $paid, 2),
            ];
        })->values()->all();

        return [
            'title' => 'Услуги для контрагента (он — заказчик)',
            'description' => 'Начислено по тарифу заказчика в заказах; оплачено — поступления по графику оплат.',
            'rows' => $rows,
            'totals' => $this->sumRows($rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCarrierSection(
        int $contractorId,
        ?Carbon $from,
        ?Carbon $to,
        ?int $userId,
        ?string $roleName,
        string $ordersScope,
    ): array {
        $orders = $this->ordersBaseQuery($userId, $roleName, $ordersScope)
            ->when($from, fn ($q) => $q->whereDate('orders.order_date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('orders.order_date', '<=', $to->toDateString()))
            ->orderByDesc('orders.order_date')
            ->orderByDesc('orders.id')
            ->get(['orders.id', 'orders.order_number', 'orders.order_date']);

        $rows = [];

        foreach ($orders as $order) {
            $accrued = $this->carrierAccruedForOrder((int) $order->id, $contractorId);

            if ($accrued <= 0) {
                continue;
            }

            $paid = $this->paidAmountForOrder((int) $order->id, 'carrier', $contractorId, $from, $to);

            $rows[] = [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'order_date' => optional($order->order_date)?->toDateString(),
                'accrued' => round($accrued, 2),
                'paid' => round($paid, 2),
                'balance' => round($accrued - $paid, 2),
            ];
        }

        return [
            'title' => 'Услуги от контрагента (он — перевозчик)',
            'description' => 'Начислено по сумме перевозки в заказах; оплачено — наши платежи по графику оплат.',
            'rows' => $rows,
            'totals' => $this->sumRows($rows),
        ];
    }

    /**
     * @param  list<int>  $orderIds
     * @return array<int, float>
     */
    private function paidAmountsByOrder(
        array $orderIds,
        string $party,
        int $contractorId,
        ?Carbon $from,
        ?Carbon $to,
    ): array {
        if ($orderIds === []) {
            return [];
        }

        if ($this->ledgerService->ledgerTableExists()) {
            $query = PaymentSchedulePaymentEvent::query()
                ->whereIn('order_id', $orderIds)
                ->where('party', $party)
                ->where('contractor_id', $contractorId);

            if ($from) {
                $query->whereDate('payment_date', '>=', $from->toDateString());
            }

            if ($to) {
                $query->whereDate('payment_date', '<=', $to->toDateString());
            }

            return $query
                ->selectRaw('order_id, SUM(amount) as total')
                ->groupBy('order_id')
                ->pluck('total', 'order_id')
                ->map(fn ($value): float => (float) $value)
                ->all();
        }

        return $this->paidAmountsFromSchedules($orderIds, $party, $contractorId, $from, $to);
    }

    private function paidAmountForOrder(
        int $orderId,
        string $party,
        int $contractorId,
        ?Carbon $from,
        ?Carbon $to,
    ): float {
        $map = $this->paidAmountsByOrder([$orderId], $party, $contractorId, $from, $to);

        return (float) ($map[$orderId] ?? 0);
    }

    /**
     * @param  list<int>  $orderIds
     * @return array<int, float>
     */
    private function paidAmountsFromSchedules(
        array $orderIds,
        string $party,
        int $contractorId,
        ?Carbon $from,
        ?Carbon $to,
    ): array {
        if (! Schema::hasTable('payment_schedules')) {
            return [];
        }

        $query = DB::table('payment_schedules')
            ->whereIn('order_id', $orderIds)
            ->where('party', $party)
            ->where('status', '!=', 'cancelled');

        if (Schema::hasColumn('payment_schedules', 'counterparty_id')) {
            $query->where('counterparty_id', $contractorId);
        }

        if (Schema::hasColumn('payment_schedules', 'is_partial')) {
            $query->where(function ($q): void {
                $q->whereNull('is_partial')->orWhere('is_partial', false);
            });
        }

        if ($from) {
            $query->whereDate('actual_date', '>=', $from->toDateString());
        }

        if ($to) {
            $query->whereDate('actual_date', '<=', $to->toDateString());
        }

        if (! Schema::hasColumn('payment_schedules', 'paid_amount')) {
            return [];
        }

        return $query
            ->selectRaw('order_id, SUM(COALESCE(paid_amount, 0)) as total')
            ->groupBy('order_id')
            ->pluck('total', 'order_id')
            ->map(fn ($value): float => (float) $value)
            ->all();
    }

    private function carrierAccruedForOrder(int $orderId, int $contractorId): float
    {
        if (! Schema::hasTable('financial_terms')) {
            return 0.0;
        }

        $costsJson = DB::table('financial_terms')
            ->where('order_id', $orderId)
            ->value('contractors_costs');

        if (! $costsJson) {
            return 0.0;
        }

        $costs = is_array($costsJson) ? $costsJson : json_decode((string) $costsJson, true);

        if (! is_array($costs)) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($costs as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rowContractorId = (int) ($row['contractor_id'] ?? $row['id'] ?? 0);

            if ($rowContractorId !== $contractorId) {
                continue;
            }

            $total += (float) ($row['amount'] ?? 0);
        }

        return $total;
    }

    private function resolveCustomerAccrued(Order $order): float
    {
        if (Schema::hasColumn('orders', 'customer_rate') && $order->customer_rate !== null) {
            return (float) $order->customer_rate;
        }

        if (! Schema::hasTable('financial_terms')) {
            return 0.0;
        }

        $clientPrice = DB::table('financial_terms')
            ->where('order_id', $order->id)
            ->value('client_price');

        return (float) ($clientPrice ?? 0);
    }

    /**
     * @return Builder<Order>
     */
    private function ordersBaseQuery(?int $userId, ?string $roleName, string $ordersScope)
    {
        return Order::query()
            ->when(
                Schema::hasColumn('orders', 'deleted_at'),
                fn ($query) => $query->whereNull('deleted_at'),
            )
            ->when(
                $userId !== null && $roleName !== 'admin' && $ordersScope !== 'all',
                fn ($query) => $query->where('manager_id', $userId),
            );
    }

    /**
     * @param  list<array{accrued: float, paid: float, balance: float}>  $rows
     * @return array{accrued: float, paid: float, balance: float}
     */
    private function sumRows(array $rows): array
    {
        $accrued = 0.0;
        $paid = 0.0;

        foreach ($rows as $row) {
            $accrued += (float) ($row['accrued'] ?? 0);
            $paid += (float) ($row['paid'] ?? 0);
        }

        return [
            'accrued' => round($accrued, 2),
            'paid' => round($paid, 2),
            'balance' => round($accrued - $paid, 2),
        ];
    }

    private function contractorDisplayName(Contractor $contractor): string
    {
        $name = trim((string) ($contractor->name ?? ''));

        if ($name !== '') {
            return $name;
        }

        return trim((string) ($contractor->full_name ?? '')) ?: 'Контрагент #'.$contractor->id;
    }

    /**
     * @return Collection<int, array{id: int, label: string, inn: ?string}>
     */
    public function contractorOptions(): Collection
    {
        return Contractor::query()
            ->orderByRaw("COALESCE(NULLIF(TRIM(name), ''), full_name)")
            ->limit(500)
            ->get(['id', 'name', 'full_name', 'inn'])
            ->map(fn (Contractor $contractor): array => [
                'id' => $contractor->id,
                'label' => $this->contractorDisplayName($contractor),
                'inn' => $contractor->inn,
                'type' => strtolower(trim((string) ($contractor->type ?? 'both'))) ?: 'both',
            ]);
    }
}
