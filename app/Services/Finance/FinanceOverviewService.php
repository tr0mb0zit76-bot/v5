<?php

namespace App\Services\Finance;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinanceOverviewService
{
    /**
     * Журнал плановых и фактических оплат по заказам (payment_schedules).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function cashFlowJournal(?int $userId, ?string $roleName, string $ordersScope): Collection
    {
        if (! Schema::hasTable('payment_schedules')) {
            return collect();
        }

        return DB::table('payment_schedules')
            ->join('orders', 'orders.id', '=', 'payment_schedules.order_id')
            ->leftJoin('contractors as customers', 'customers.id', '=', 'orders.customer_id')
            ->leftJoin('contractors as carriers', 'carriers.id', '=', 'orders.carrier_id')
            ->leftJoin('users as managers', 'managers.id', '=', 'orders.manager_id')
            ->when(
                $userId !== null && $roleName !== 'admin' && $ordersScope !== 'all',
                fn ($query) => $query->where('orders.manager_id', $userId)
            )
            ->when(
                Schema::hasColumn('orders', 'deleted_at'),
                fn ($query) => $query->whereNull('orders.deleted_at')
            )
            ->select([
                'payment_schedules.id',
                'payment_schedules.party',
                'payment_schedules.type',
                'payment_schedules.amount',
                'payment_schedules.planned_date',
                'payment_schedules.actual_date',
                'payment_schedules.status',
                'orders.id as order_id',
                'orders.order_number',
                'managers.name as manager_name',
                'customers.name as customer_name',
                'carriers.name as carrier_name',
            ])
            ->orderByDesc('payment_schedules.planned_date')
            ->orderByDesc('payment_schedules.id')
            ->get()
            ->map(fn (object $row): array => [
                'id' => $row->id,
                'order_id' => $row->order_id,
                'order_number' => $row->order_number,
                'manager_name' => $row->manager_name,
                'direction' => $row->party === 'customer' ? 'Нам' : 'Мы',
                'counterparty_name' => $row->party === 'customer' ? $row->customer_name : $row->carrier_name,
                'payment_type' => $row->type === 'prepayment' ? 'Предоплата' : 'Финальный платёж',
                'amount' => (float) ($row->amount ?? 0),
                'planned_date' => $row->planned_date,
                'actual_date' => $row->actual_date,
                'status' => $row->status,
            ]);
    }

    /**
     * Агрегаты по графику оплат: периоды, дебиторка, кредиторка (всего / в срок по плану / просрочено).
     *
     * @return array<string, mixed>
     */
    public function cashFlowStats(?int $userId, ?string $roleName, string $ordersScope): array
    {
        if (! Schema::hasTable('payment_schedules')) {
            return $this->defaultCashFlowStats();
        }

        $today = Carbon::today();
        $weekEnd = $today->copy()->addDays(6);
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();

        $rows = DB::table('payment_schedules')
            ->join('orders', 'orders.id', '=', 'payment_schedules.order_id')
            ->when(
                $userId !== null && $roleName !== 'admin' && $ordersScope !== 'all',
                fn ($query) => $query->where('orders.manager_id', $userId),
            )
            ->when(
                Schema::hasColumn('orders', 'deleted_at'),
                fn ($query) => $query->whereNull('orders.deleted_at'),
            )
            ->selectRaw(<<<'SQL'
                payment_schedules.party,
                SUM(CASE WHEN payment_schedules.planned_date = ? THEN payment_schedules.amount ELSE 0 END) as today,
                SUM(CASE WHEN payment_schedules.planned_date BETWEEN ? AND ? THEN payment_schedules.amount ELSE 0 END) as week,
                SUM(CASE WHEN payment_schedules.planned_date BETWEEN ? AND ? THEN payment_schedules.amount ELSE 0 END) as month,
                SUM(CASE WHEN payment_schedules.status IN (?, ?) THEN payment_schedules.amount ELSE 0 END) as outstanding,
                SUM(CASE WHEN payment_schedules.status = ? THEN payment_schedules.amount ELSE 0 END) as pending_only,
                SUM(CASE WHEN payment_schedules.status = ? THEN payment_schedules.amount ELSE 0 END) as overdue
            SQL, [
                $today,
                $today,
                $weekEnd,
                $monthStart,
                $monthEnd,
                'pending',
                'overdue',
                'pending',
                'overdue',
            ])
            ->groupBy('payment_schedules.party')
            ->get()
            ->keyBy('party');

        $customerRow = $rows->get('customer');
        $carrierRow = $rows->get('carrier');

        return [
            'periods' => [
                'today' => [
                    'incoming' => (float) (optional($customerRow)->today ?? 0),
                    'outgoing' => (float) (optional($carrierRow)->today ?? 0),
                ],
                'week' => [
                    'incoming' => (float) (optional($customerRow)->week ?? 0),
                    'outgoing' => (float) (optional($carrierRow)->week ?? 0),
                ],
                'month' => [
                    'incoming' => (float) (optional($customerRow)->month ?? 0),
                    'outgoing' => (float) (optional($carrierRow)->month ?? 0),
                ],
            ],
            'receivables' => [
                'total' => (float) (optional($customerRow)->outstanding ?? 0),
                'pending' => (float) (optional($customerRow)->pending_only ?? 0),
                'overdue' => (float) (optional($customerRow)->overdue ?? 0),
            ],
            'payables' => [
                'total' => (float) (optional($carrierRow)->outstanding ?? 0),
                'pending' => (float) (optional($carrierRow)->pending_only ?? 0),
                'overdue' => (float) (optional($carrierRow)->overdue ?? 0),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultCashFlowStats(): array
    {
        return [
            'periods' => [
                'today' => ['incoming' => 0.0, 'outgoing' => 0.0],
                'week' => ['incoming' => 0.0, 'outgoing' => 0.0],
                'month' => ['incoming' => 0.0, 'outgoing' => 0.0],
            ],
            'receivables' => ['total' => 0.0, 'pending' => 0.0, 'overdue' => 0.0],
            'payables' => ['total' => 0.0, 'pending' => 0.0, 'overdue' => 0.0],
        ];
    }

    /**
     * @return array{name: string|null, visibility_scopes: array<string, string>}
     */
    public function resolveRole(?int $roleId): array
    {
        if ($roleId === null) {
            return [
                'name' => null,
                'visibility_scopes' => [],
            ];
        }

        $select = ['name'];

        if (Schema::hasColumn('roles', 'visibility_scopes')) {
            $select[] = 'visibility_scopes';
        }

        $role = DB::table('roles')
            ->where('id', $roleId)
            ->select($select)
            ->first();

        if ($role === null) {
            return [
                'name' => null,
                'visibility_scopes' => [],
            ];
        }

        $visibilityScopes = property_exists($role, 'visibility_scopes')
            ? $role->visibility_scopes
            : [];

        if (is_string($visibilityScopes)) {
            $visibilityScopes = json_decode($visibilityScopes, true);
        }

        return [
            'name' => $role->name,
            'visibility_scopes' => is_array($visibilityScopes) ? $visibilityScopes : [],
        ];
    }
}
