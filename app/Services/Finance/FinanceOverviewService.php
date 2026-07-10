<?php

namespace App\Services\Finance;

use App\Models\User;
use App\Support\OrderViewAuthorization;
use App\Support\PaymentScheduleSettlementStatus;
use App\Support\RoleAccess;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinanceOverviewService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function cashFlowJournal(?User $user = null): Collection
    {
        if (! Schema::hasTable('payment_schedules')) {
            return collect();
        }

        $area = $user !== null
            ? RoleAccess::resolvePaymentScheduleVisibilityAreaForUser($user)
            : 'orders';

        $journalQuery = DB::table('payment_schedules')
            ->join('orders', 'orders.id', '=', 'payment_schedules.order_id')
            ->leftJoin('contractors as customers', 'customers.id', '=', 'orders.customer_id')
            ->when(
                Schema::hasColumn('payment_schedules', 'counterparty_id'),
                fn ($query) => $query->leftJoin('contractors as carriers', function ($join): void {
                    $join->whereRaw('carriers.id = COALESCE(payment_schedules.counterparty_id, orders.carrier_id)');
                }),
                fn ($query) => $query->leftJoin('contractors as carriers', 'carriers.id', '=', 'orders.carrier_id'),
            )
            ->leftJoin('users as managers', 'managers.id', '=', 'orders.manager_id');

        if ($user !== null) {
            OrderViewAuthorization::applyOrdersVisibilityScopeToQuery($journalQuery, $user, $area);
        }

        $journalQuery
            ->when(
                Schema::hasColumn('orders', 'deleted_at'),
                fn ($query) => $query->whereNull('orders.deleted_at')
            );

        PaymentScheduleSettlementStatus::applyUnsettledRootScope($journalQuery);

        $journalQuery
            ->when(
                Schema::hasColumn('payment_schedules', 'parent_payment_id'),
                fn ($query) => $query->whereNull('payment_schedules.parent_payment_id'),
            )
            ->when(
                Schema::hasColumn('payment_schedules', 'is_partial'),
                fn ($query) => $query->where(function ($q): void {
                    $q->whereNull('payment_schedules.is_partial')
                        ->orWhere('payment_schedules.is_partial', false);
                }),
            );

        $select = [
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
            DB::raw($this->sqlContractorDisplayName('customers').' as customer_name'),
            DB::raw($this->sqlContractorDisplayName('carriers').' as carrier_name'),
        ];

        if (Schema::hasColumn('payment_schedules', 'paid_amount')) {
            $select[] = DB::raw('COALESCE(payment_schedules.paid_amount, 0) as paid_amount');
        } else {
            $select[] = DB::raw('0 as paid_amount');
        }

        if (Schema::hasColumn('payment_schedules', 'remaining_amount')) {
            $select[] = DB::raw(
                $this->paymentScheduleOutstandingAmountSql().' as remaining_amount'
            );
        } else {
            $select[] = DB::raw('payment_schedules.amount as remaining_amount');
        }

        if (Schema::hasColumn('payment_schedules', 'counterparty_id')) {
            $select[] = 'payment_schedules.counterparty_id';
        }

        if (Schema::hasColumn('payment_schedules', 'invoice_number')) {
            $select[] = 'payment_schedules.invoice_number';
        }

        if (Schema::hasColumn('payment_schedules', 'payment_run_date')) {
            $select[] = 'payment_schedules.payment_run_date';
        }

        if (Schema::hasColumn('payment_schedules', 'payment_run_note')) {
            $select[] = 'payment_schedules.payment_run_note';
        }

        if (Schema::hasColumn('payment_schedules', 'payment_run_by')) {
            $select[] = 'payment_schedules.payment_run_by';
        }

        if (Schema::hasColumn('payment_schedules', 'is_partial')) {
            $select[] = DB::raw('COALESCE(payment_schedules.is_partial, 0) as is_partial');
        } else {
            $select[] = DB::raw('0 as is_partial');
        }

        if (Schema::hasColumn('payment_schedules', 'parent_payment_id')) {
            $select[] = 'payment_schedules.parent_payment_id';
        }

        $rows = $journalQuery
            ->select($select)
            ->orderByDesc('payment_schedules.planned_date')
            ->orderByDesc('payment_schedules.id')
            ->get();

        $carrierSummaries = $this->assignedCarrierSummariesByOrderIds(
            $rows->pluck('order_id')->map(fn ($id): int => (int) $id)->unique()->values()->all()
        );

        return $rows->map(fn (object $row): array => $this->mapCashFlowJournalRow($row, $carrierSummaries));
    }

    /**
     * @param  Collection<int, array{count: int, label: string}>  $carrierSummaries
     * @return array<string, mixed>
     */
    private function mapCashFlowJournalRow(object $row, Collection $carrierSummaries): array
    {
        $party = strtolower(trim((string) ($row->party ?? '')));
        $isCustomerParty = ($party === 'customer');

        // Получаем данные о частичных платежах
        $paidAmount = (float) ($row->paid_amount ?? 0);
        $remainingAmount = (float) ($row->remaining_amount ?? 0);
        $isPartial = (bool) ($row->is_partial ?? false);
        $parentPaymentId = $row->parent_payment_id ?? null;

        // Рассчитываем прогресс оплаты
        $paymentProgress = 0;
        if ($paidAmount > 0 && $row->amount > 0) {
            $paymentProgress = min(100, ($paidAmount / (float) $row->amount) * 100);
        }

        $amountDue = $remainingAmount > 0.009
            ? $remainingAmount
            : ($paidAmount > 0.009 ? 0.0 : (float) ($row->amount ?? 0));
        $isPartiallySettled = $paidAmount > 0.009 && $remainingAmount > 0.009;

        return [
            'id' => $row->id,
            'order_id' => $row->order_id,
            'order_number' => $row->order_number,
            'manager_name' => $row->manager_name,
            'direction' => $isCustomerParty ? 'Нам' : 'Мы',
            'counterparty_name' => $isCustomerParty
                ? $row->customer_name
                : $this->resolveCarrierDisplayName($row, $carrierSummaries),
            'counterparty_id' => Schema::hasColumn('payment_schedules', 'counterparty_id')
                ? ($row->counterparty_id ?? null)
                : null,
            'payment_type' => $row->type === 'prepayment' ? 'Предоплата' : 'Финальный платёж',
            'amount' => (float) ($row->amount ?? 0),
            'amount_due' => $amountDue,
            'planned_date' => $row->planned_date,
            'actual_date' => $row->actual_date,
            'status' => $row->status,
            // Поля для частичных платежей
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'is_partial' => $isPartial,
            'parent_payment_id' => $parentPaymentId,
            'payment_progress' => $paymentProgress,
            'has_partial_payments' => $parentPaymentId === null && $paidAmount > 0 && $remainingAmount > 0,
            'is_partially_settled' => $isPartiallySettled,
            'invoice_number' => data_get($row, 'invoice_number'),
            'payment_run_date' => data_get($row, 'payment_run_date'),
            'payment_run_note' => data_get($row, 'payment_run_note'),
            'payment_run_by' => data_get($row, 'payment_run_by'),
        ];
    }

    /**
     * @param  Collection<int, array{count: int, label: string}>  $carrierSummaries
     */
    private function resolveCarrierDisplayName(object $row, Collection $carrierSummaries): ?string
    {
        $joinName = trim((string) ($row->carrier_name ?? ''));

        if ($joinName !== '') {
            return $joinName;
        }

        if (! Schema::hasTable('leg_contractor_assignments')) {
            return null;
        }

        $summary = $carrierSummaries->get((int) $row->order_id);

        if ($summary === null) {
            return null;
        }

        $count = (int) ($summary['count'] ?? 0);

        if ($count > 1) {
            return $this->carrierCountLabel($count);
        }

        $label = trim((string) ($summary['label'] ?? ''));

        return $label !== '' ? $label : null;
    }

    private function carrierCountLabel(int $count): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return $count.' перевозчик';
        }

        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 10 || $mod100 >= 20)) {
            return $count.' перевозчика';
        }

        return $count.' перевозчиков';
    }

    /**
     * Сводка по назначенным на плечи перевозчикам (как на списке заказов).
     *
     * @param  list<int>  $orderIds
     * @return Collection<int, array{count: int, label: string}>
     */
    private function assignedCarrierSummariesByOrderIds(array $orderIds): Collection
    {
        if ($orderIds === [] || ! Schema::hasTable('leg_contractor_assignments') || ! Schema::hasTable('order_legs')) {
            return collect();
        }

        $labelSql = $this->sqlContractorDisplayName('lcc');

        $rows = DB::table('order_legs')
            ->join('leg_contractor_assignments as lca', 'lca.order_leg_id', '=', 'order_legs.id')
            ->join('contractors as lcc', 'lcc.id', '=', 'lca.contractor_id')
            ->whereIn('order_legs.order_id', $orderIds)
            ->orderByRaw($labelSql)
            ->select([
                'order_legs.order_id',
                'lca.contractor_id',
                DB::raw($labelSql.' as contractor_label'),
            ])
            ->get();

        return $rows
            ->groupBy('order_id')
            ->map(function (Collection $group): array {
                $uniqueContractorIds = $group->pluck('contractor_id')->unique()->filter()->values();
                $count = $uniqueContractorIds->count();

                if ($count > 1) {
                    return [
                        'count' => $count,
                        'label' => $this->carrierCountLabel($count),
                    ];
                }

                $singleName = (string) $group->pluck('contractor_label')->unique()->filter()->values()->first();

                return [
                    'count' => $count,
                    'label' => $singleName,
                ];
            });
    }

    /**
     * Отображаемое имя контрагента: у недавно созданных карточек часто заполняют только full_name, а name остаётся пустым.
     */
    private function sqlContractorDisplayName(string $tableAlias): string
    {
        if (! in_array($tableAlias, ['customers', 'carriers', 'lcc'], true)) {
            throw new \InvalidArgumentException('Invalid contractors table alias.');
        }

        if (! Schema::hasColumn('contractors', 'full_name')) {
            return "{$tableAlias}.name";
        }

        return "COALESCE(NULLIF(TRIM({$tableAlias}.name), ''), {$tableAlias}.full_name)";
    }

    /**
     * Базовый запрос: только «открытые» корневые строки графика (как в журнале «Открытые»).
     */
    private function paymentSchedulesOpenRootsBaseQuery(?User $user)
    {
        $area = $user !== null
            ? RoleAccess::resolvePaymentScheduleVisibilityAreaForUser($user)
            : 'orders';

        $query = DB::table('payment_schedules')
            ->join('orders', 'orders.id', '=', 'payment_schedules.order_id');

        if ($user !== null) {
            OrderViewAuthorization::applyOrdersVisibilityScopeToQuery($query, $user, $area);
        }

        $query
            ->when(
                Schema::hasColumn('orders', 'deleted_at'),
                fn ($query) => $query->whereNull('orders.deleted_at'),
            );

        PaymentScheduleSettlementStatus::applyUnsettledRootScope($query);

        return $query
            ->when(
                Schema::hasColumn('payment_schedules', 'parent_payment_id'),
                fn ($query) => $query->whereNull('payment_schedules.parent_payment_id'),
            )
            ->when(
                Schema::hasColumn('payment_schedules', 'is_partial'),
                fn ($query) => $query->where(function ($q): void {
                    $q->whereNull('payment_schedules.is_partial')
                        ->orWhere('payment_schedules.is_partial', false);
                }),
            );
    }

    /**
     * Остаток к оплате по строке графика: при частичной оплате — remaining_amount, иначе полная сумма.
     */
    private function paymentScheduleEffectiveAmountSql(): string
    {
        return PaymentScheduleSettlementStatus::outstandingAmountSql();
    }

    private function paymentScheduleOutstandingAmountSql(): string
    {
        return PaymentScheduleSettlementStatus::outstandingAmountSql();
    }

    /**
     * Агрегаты по графику оплат: периоды, дебиторка, кредиторка (всего / в срок по плану / просрочено).
     * Учитываются только открытые корневые строки (не paid/cancelled, не дочерние частичные).
     * Суммы — по остатку к оплате, если есть колонка remaining_amount.
     *
     * @return array<string, mixed>
     */
    public function cashFlowStats(?User $user = null): array
    {
        if (! Schema::hasTable('payment_schedules')) {
            return $this->defaultCashFlowStats();
        }

        $today = Carbon::today();
        $weekEnd = $today->copy()->addDays(6);
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();

        $effective = $this->paymentScheduleEffectiveAmountSql();

        $rows = $this->paymentSchedulesOpenRootsBaseQuery($user)
            ->selectRaw(
                'LOWER(TRIM(payment_schedules.party)) as party, '.
                "SUM(CASE WHEN payment_schedules.planned_date = ? THEN {$effective} ELSE 0 END) as today, ".
                "SUM(CASE WHEN payment_schedules.planned_date BETWEEN ? AND ? THEN {$effective} ELSE 0 END) as week, ".
                "SUM(CASE WHEN payment_schedules.planned_date BETWEEN ? AND ? THEN {$effective} ELSE 0 END) as month, ".
                "SUM(CASE WHEN payment_schedules.status IN (?, ?) THEN {$effective} ELSE 0 END) as outstanding, ".
                "SUM(CASE WHEN payment_schedules.status = ? THEN {$effective} ELSE 0 END) as pending_only, ".
                "SUM(CASE WHEN payment_schedules.status = ? OR (payment_schedules.status = ? AND payment_schedules.planned_date < ?) THEN {$effective} ELSE 0 END) as overdue",
                [
                    $today,
                    $today,
                    $weekEnd,
                    $monthStart,
                    $monthEnd,
                    'pending',
                    'overdue',
                    'pending',
                    'overdue',
                    'pending',
                    $today,
                ]
            )
            ->groupBy(DB::raw('LOWER(TRIM(payment_schedules.party))'))
            ->get()
            ->keyBy('party');

        $customerRow = $rows->get('customer');
        $carrierRow = $rows->get('carrier');
        $contractorRow = $rows->get('contractor');

        $outgoingToday = (float) (optional($carrierRow)->today ?? 0) + (float) (optional($contractorRow)->today ?? 0);
        $outgoingWeek = (float) (optional($carrierRow)->week ?? 0) + (float) (optional($contractorRow)->week ?? 0);
        $outgoingMonth = (float) (optional($carrierRow)->month ?? 0) + (float) (optional($contractorRow)->month ?? 0);
        $outstandingOutgoing = (float) (optional($carrierRow)->outstanding ?? 0) + (float) (optional($contractorRow)->outstanding ?? 0);
        $pendingOutgoing = (float) (optional($carrierRow)->pending_only ?? 0) + (float) (optional($contractorRow)->pending_only ?? 0);
        $overdueOutgoing = (float) (optional($carrierRow)->overdue ?? 0) + (float) (optional($contractorRow)->overdue ?? 0);

        return [
            'periods' => [
                'today' => [
                    'incoming' => (float) (optional($customerRow)->today ?? 0),
                    'outgoing' => $outgoingToday,
                ],
                'week' => [
                    'incoming' => (float) (optional($customerRow)->week ?? 0),
                    'outgoing' => $outgoingWeek,
                ],
                'month' => [
                    'incoming' => (float) (optional($customerRow)->month ?? 0),
                    'outgoing' => $outgoingMonth,
                ],
            ],
            'receivables' => [
                'total' => (float) (optional($customerRow)->outstanding ?? 0),
                'pending' => (float) (optional($customerRow)->pending_only ?? 0),
                'overdue' => (float) (optional($customerRow)->overdue ?? 0),
            ],
            'payables' => [
                'total' => $outstandingOutgoing,
                'pending' => $pendingOutgoing,
                'overdue' => $overdueOutgoing,
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
            ? RoleAccess::coerceVisibilityScopes($role->visibility_scopes)
            : null;

        return [
            'name' => $role->name,
            'visibility_scopes' => is_array($visibilityScopes) ? $visibilityScopes : [],
        ];
    }
}
