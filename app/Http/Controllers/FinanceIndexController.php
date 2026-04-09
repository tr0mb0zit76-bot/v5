<?php

namespace App\Http\Controllers;

use App\Models\FinanceDocument;
use App\Support\RoleAccess;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class FinanceIndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $role = $this->resolveRole($user?->role_id);
        $ordersScope = RoleAccess::resolveVisibilityScope($role['name'], $role['visibility_scopes'], 'orders');

        $invoices = $this->invoiceRows($user?->id, $role['name'], $ordersScope);
        $upds = $this->updRows($user?->id, $role['name'], $ordersScope);
        $cashFlow = $this->cashFlowRows($user?->id, $role['name'], $ordersScope);
        $activeSubmodule = match ($request->query('section')) {
            'dds' => 'dds',
            'documents' => 'documents',
            default => 'overview',
        };
        $cashFlowStats = $this->cashFlowStats($user?->id, $role['name'], $ordersScope);

        return Inertia::render('Finance/Index', [
            'summary' => [
                'invoices_total' => $invoices->count(),
                'invoices_issued' => $invoices->where('is_issued', true)->count(),
                'upds_total' => $upds->count(),
                'upds_ready' => $upds->where('has_any_upd', true)->count(),
                'cash_flow_total' => $cashFlow->count(),
                'cash_flow_pending' => $cashFlow->where('status', 'pending')->count(),
            ],
            'invoices' => $invoices->values(),
            'upds' => $upds->values(),
            'cashFlowJournal' => $cashFlow->values(),
            'orders' => $this->availableOrders($user?->id, $role['name'], $ordersScope)->values(),
            'documents' => $this->documents($user?->id, $role['name'], $ordersScope),
            'active_submodule' => $activeSubmodule,
            'todays_cash_flow' => $cashFlowStats['periods']['today'],
            'cash_flow_stats' => $cashFlowStats,
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function invoiceRows(?int $userId, ?string $roleName, string $ordersScope): Collection
    {
        return $this->baseOrdersQuery($userId, $roleName, $ordersScope)
            ->whereNotNull('orders.customer_rate')
            ->select([
                'orders.id',
                'orders.order_number',
                'orders.order_date',
                'orders.status',
                'customers.name as customer_name',
                'managers.name as manager_name',
                'orders.customer_rate',
                'orders.customer_payment_form',
                'orders.invoice_number',
            ])
            ->orderByDesc('orders.order_date')
            ->orderByDesc('orders.id')
            ->get()
            ->map(fn (object $row): array => [
                'id' => $row->id,
                'order_number' => $row->order_number,
                'order_date' => $row->order_date,
                'customer_name' => $row->customer_name,
                'manager_name' => $row->manager_name,
                'amount' => (float) ($row->customer_rate ?? 0),
                'payment_form' => $row->customer_payment_form,
                'invoice_number' => $row->invoice_number,
                'status' => $row->status,
                'is_issued' => filled($row->invoice_number),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function updRows(?int $userId, ?string $roleName, string $ordersScope): Collection
    {
        return $this->baseOrdersQuery($userId, $roleName, $ordersScope)
            ->where(function ($query) {
                $query->whereNotNull('orders.customer_rate');

                $this->applyCarrierCostExistsCondition($query);
            })
            ->select([
                'orders.id',
                'orders.order_number',
                'orders.order_date',
                'orders.status',
                'customers.name as customer_name',
                'carriers.name as carrier_name',
                'orders.upd_number',
                'orders.upd_carrier_number',
            ])
            ->orderByDesc('orders.order_date')
            ->orderByDesc('orders.id')
            ->get()
            ->map(fn (object $row): array => [
                'id' => $row->id,
                'order_number' => $row->order_number,
                'order_date' => $row->order_date,
                'customer_name' => $row->customer_name,
                'carrier_name' => $row->carrier_name,
                'customer_upd_number' => $row->upd_number,
                'carrier_upd_number' => $row->upd_carrier_number,
                'status' => $row->status,
                'has_any_upd' => filled($row->upd_number) || filled($row->upd_carrier_number),
            ]);
    }

    /**
     * Adds exists-condition for leg costs using available schema.
     */
    private function applyCarrierCostExistsCondition(Builder $query): void
    {
        if (! Schema::hasTable('order_legs') || ! Schema::hasTable('leg_costs')) {
            return;
        }

        $amountColumn = Schema::hasColumn('leg_costs', 'amount');
        $legacyCarrierRateColumn = Schema::hasColumn('leg_costs', 'carrier_rate');

        if (! $amountColumn && ! $legacyCarrierRateColumn) {
            return;
        }

        $costColumn = $amountColumn ? 'leg_costs.amount' : 'leg_costs.carrier_rate';

        $query->orWhereExists(function ($subQuery) use ($costColumn) {
            $subQuery->select(DB::raw(1))
                ->from('order_legs')
                ->join('leg_costs', 'leg_costs.order_leg_id', '=', 'order_legs.id')
                ->whereColumn('order_legs.order_id', 'orders.id')
                ->whereNotNull($costColumn);
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function cashFlowRows(?int $userId, ?string $roleName, string $ordersScope): Collection
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

    private function cashFlowStats(?int $userId, ?string $roleName, string $ordersScope): array
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
                SUM(CASE WHEN payment_schedules.status = ? THEN payment_schedules.amount ELSE 0 END) as overdue
            SQL, [
                $today,
                $today,
                $weekEnd,
                $monthStart,
                $monthEnd,
                'pending',
                'overdue',
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
                'overdue' => (float) (optional($customerRow)->overdue ?? 0),
            ],
            'payables' => [
                'total' => (float) (optional($carrierRow)->outstanding ?? 0),
                'overdue' => (float) (optional($carrierRow)->overdue ?? 0),
            ],
        ];
    }

    private function defaultCashFlowStats(): array
    {
        return [
            'periods' => [
                'today' => ['incoming' => 0.0, 'outgoing' => 0.0],
                'week' => ['incoming' => 0.0, 'outgoing' => 0.0],
                'month' => ['incoming' => 0.0, 'outgoing' => 0.0],
            ],
            'receivables' => ['total' => 0.0, 'overdue' => 0.0],
            'payables' => ['total' => 0.0, 'overdue' => 0.0],
        ];
    }

    private function baseOrdersQuery(?int $userId, ?string $roleName, string $ordersScope)
    {
        return DB::table('orders')
            ->leftJoin('users as managers', 'managers.id', '=', 'orders.manager_id')
            ->leftJoin('contractors as customers', 'customers.id', '=', 'orders.customer_id')
            ->leftJoin('contractors as carriers', 'carriers.id', '=', 'orders.carrier_id')
            ->when(
                $userId !== null && $roleName !== 'admin' && $ordersScope !== 'all',
                fn ($query) => $query->where('orders.manager_id', $userId)
            )
            ->when(
                Schema::hasColumn('orders', 'deleted_at'),
                fn ($query) => $query->whereNull('orders.deleted_at')
            );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function availableOrders(?int $userId, ?string $roleName, string $ordersScope): Collection
    {
        return $this->baseOrdersQuery($userId, $roleName, $ordersScope)
            ->select([
                'orders.id',
                'orders.order_number',
                'orders.order_date',
                'customers.name as customer_name',
            ])
            ->orderByDesc('orders.order_date')
            ->orderByDesc('orders.id')
            ->get()
            ->map(fn (object $row) => [
                'id' => $row->id,
                'order_number' => $row->order_number,
                'order_date' => $row->order_date,
                'customer_name' => $row->customer_name,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function documents(?int $userId, ?string $roleName, string $ordersScope): Collection
    {
        if (! Schema::hasTable('finance_documents')) {
            return collect();
        }

        return FinanceDocument::query()
            ->with('order')
            ->whereHas('order', function ($query) use ($userId, $roleName, $ordersScope) {
                $query->when(
                    $userId !== null && $roleName !== 'admin' && $ordersScope !== 'all',
                    fn ($subQuery) => $subQuery->where('manager_id', $userId)
                );
            })
            ->orderByDesc('id')
            ->get()
            ->map(fn (FinanceDocument $document) => [
                'id' => $document->id,
                'order_id' => $document->order_id,
                'order_number' => optional($document->order)->order_number,
                'document_type' => $document->document_type,
                'status' => $document->status,
                'number' => $document->number,
                'amount' => $document->amount,
                'issue_date' => $document->issue_date?->toDateString(),
            ]);
    }

    /**
     * @return array{name: string|null, visibility_scopes: array<string, string>}
     */
    private function resolveRole(?int $roleId): array
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
