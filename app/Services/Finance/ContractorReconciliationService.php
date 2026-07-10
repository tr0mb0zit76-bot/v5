<?php

namespace App\Services\Finance;

use App\Models\Contractor;
use App\Models\Order;
use App\Models\PaymentSchedulePaymentEvent;
use App\Models\User;
use App\Support\OrderViewAuthorization;
use App\Support\RoleAccess;
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
        ?User $user = null,
    ): array {
        $contractor = Contractor::query()->findOrFail($contractorId);
        $contractorType = strtolower(trim((string) ($contractor->type ?? 'both')));

        $from = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : null;
        $to = $dateTo ? Carbon::parse($dateTo)->endOfDay() : null;

        $asCustomer = $this->buildCustomerSection($contractorId, $from, $to, $user);
        $asCarrier = $this->buildCarrierSection($contractorId, $contractorType, $from, $to, $user);

        return [
            'contractor' => [
                'id' => $contractor->id,
                'name' => $this->contractorDisplayName($contractor),
                'inn' => $contractor->inn,
                'type' => $contractorType !== '' ? $contractorType : 'both',
            ],
            'show_as_customer' => in_array($contractorType, ['customer', 'both'], true),
            'show_as_carrier' => in_array($contractorType, ['carrier', 'contractor', 'both'], true),
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
        ?User $user,
    ): array {
        $orders = $this->ordersBaseQuery($user)
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
        $tranchesByOrder = $this->tranchesByOrder($orderIds, 'customer', $contractorId, $from, $to);

        $rows = $orders->map(function (Order $order) use ($paidByOrder, $tranchesByOrder): array {
            $accrued = $this->resolveCustomerAccrued($order);
            $paid = (float) ($paidByOrder[(int) $order->id] ?? 0);

            return [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'order_date' => optional($order->order_date)?->toDateString(),
                'accrued' => round($accrued, 2),
                'paid' => round($paid, 2),
                'balance' => round($accrued - $paid, 2),
                'tranches' => $tranchesByOrder[(int) $order->id] ?? [],
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
        string $contractorType,
        ?Carbon $from,
        ?Carbon $to,
        ?User $user,
    ): array {
        $counterpartyParty = $contractorType === 'contractor' ? 'contractor' : 'carrier';

        $orders = $this->ordersBaseQuery($user)
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

            $paid = $this->paidAmountForOrder((int) $order->id, $counterpartyParty, $contractorId, $from, $to);
            $tranches = $this->tranchesForOrder((int) $order->id, $counterpartyParty, $contractorId, $from, $to);

            $rows[] = [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'order_date' => optional($order->order_date)?->toDateString(),
                'accrued' => round($accrued, 2),
                'paid' => round($paid, 2),
                'balance' => round($accrued - $paid, 2),
                'tranches' => $tranches,
            ];
        }

        return [
            'title' => $contractorType === 'contractor'
                ? 'Услуги от контрагента (он — подрядчик)'
                : 'Услуги от контрагента (он — перевозчик)',
            'description' => $contractorType === 'contractor'
                ? 'Начислено по сумме подрядных услуг в заказах; оплачено — наши платежи по графику оплат.'
                : 'Начислено по сумме перевозки в заказах; оплачено — наши платежи по графику оплат.',
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
                ->active()
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
     * @return array<int, list<array<string, mixed>>>
     */
    private function tranchesByOrder(
        array $orderIds,
        string $party,
        int $contractorId,
        ?Carbon $from,
        ?Carbon $to,
    ): array {
        $result = [];

        foreach ($orderIds as $orderId) {
            $tranches = $this->tranchesForOrder((int) $orderId, $party, $contractorId, $from, $to);

            if ($tranches !== []) {
                $result[(int) $orderId] = $tranches;
            }
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tranchesForOrder(
        int $orderId,
        string $party,
        int $contractorId,
        ?Carbon $from,
        ?Carbon $to,
    ): array {
        if (! Schema::hasTable('payment_schedules')) {
            return [];
        }

        $query = DB::table('payment_schedules')
            ->where('order_id', $orderId)
            ->where('party', $party)
            ->where('status', '!=', 'cancelled');

        if ($party !== 'customer' && Schema::hasColumn('payment_schedules', 'counterparty_id')) {
            $query->where('counterparty_id', $contractorId);
        }

        if (Schema::hasColumn('payment_schedules', 'is_partial')) {
            $query->where(function ($partialQuery): void {
                $partialQuery->whereNull('is_partial')
                    ->orWhere('is_partial', false);
            });
        }

        $schedules = $query
            ->orderBy('planned_date')
            ->orderBy('id')
            ->get([
                'id',
                'planned_date',
                'amount',
                'paid_amount',
                'remaining_amount',
                'status',
                'invoice_number',
            ]);

        if ($schedules->isEmpty()) {
            return [];
        }

        $scheduleIds = $schedules->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $paymentsBySchedule = $this->paymentsByScheduleIds($scheduleIds, $contractorId, $from, $to);

        return $schedules
            ->map(function ($schedule) use ($paymentsBySchedule): array {
                $accrued = round((float) $schedule->amount, 2);
                $payments = $paymentsBySchedule[(int) $schedule->id] ?? [];
                $paid = round(array_sum(array_column($payments, 'amount')), 2);

                if ($paid <= 0 && $schedule->paid_amount !== null) {
                    $paid = round((float) $schedule->paid_amount, 2);
                }

                return [
                    'id' => (int) $schedule->id,
                    'planned_date' => $schedule->planned_date,
                    'invoice_number' => $schedule->invoice_number,
                    'accrued' => $accrued,
                    'paid' => $paid,
                    'balance' => round($accrued - $paid, 2),
                    'status' => $schedule->status,
                    'payments' => $payments,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $scheduleIds
     * @return array<int, list<array{date: ?string, amount: float, reference: ?string, method: ?string}>>
     */
    private function paymentsByScheduleIds(
        array $scheduleIds,
        int $contractorId,
        ?Carbon $from,
        ?Carbon $to,
    ): array {
        if ($scheduleIds === [] || ! $this->ledgerService->ledgerTableExists()) {
            return [];
        }

        $query = PaymentSchedulePaymentEvent::query()
            ->active()
            ->whereIn('payment_schedule_id', $scheduleIds)
            ->where('contractor_id', $contractorId);

        if ($from) {
            $query->whereDate('payment_date', '>=', $from->toDateString());
        }

        if ($to) {
            $query->whereDate('payment_date', '<=', $to->toDateString());
        }

        $grouped = [];

        foreach ($query->orderBy('payment_date')->orderBy('id')->get() as $event) {
            $scheduleId = (int) $event->payment_schedule_id;

            $grouped[$scheduleId][] = [
                'date' => $event->payment_date?->toDateString(),
                'amount' => round((float) $event->amount, 2),
                'reference' => $event->transaction_reference,
                'method' => $event->payment_method,
            ];
        }

        return $grouped;
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
    private function ordersBaseQuery(?User $user): Builder
    {
        $query = Order::query()
            ->when(
                Schema::hasColumn('orders', 'deleted_at'),
                fn ($query) => $query->whereNull('deleted_at'),
            );

        if ($user !== null) {
            $area = RoleAccess::resolvePaymentScheduleVisibilityAreaForUser($user);
            OrderViewAuthorization::applyOrdersVisibilityScope($query, $user, $area);
        }

        return $query;
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
