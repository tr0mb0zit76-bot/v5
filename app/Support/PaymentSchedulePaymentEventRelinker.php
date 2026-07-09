<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Перепривязывает записи журнала оплат к новым строкам графика после пересборки payment_schedules.
 */
final class PaymentSchedulePaymentEventRelinker
{
    public function ledgerTableExists(): bool
    {
        return Schema::hasTable('payment_schedule_payment_events');
    }

    public function relinkOrphanedEventsForOrder(int $orderId): int
    {
        if (! $this->ledgerTableExists() || ! Schema::hasTable('payment_schedules')) {
            return 0;
        }

        $orphanedEventIds = $this->orphanedEventIdsForOrder($orderId);
        if ($orphanedEventIds === []) {
            return 0;
        }

        $roots = $this->rootSchedulesForOrder($orderId);
        if ($roots === []) {
            return 0;
        }

        $orderPartyContractorIds = $this->orderPartyContractorIds($orderId);

        $rootsByGroup = [];
        foreach ($roots as $root) {
            $groupKey = $this->rootGroupKey(
                (string) $root->party,
                $this->resolveContractorIdForRoot($root, $orderPartyContractorIds),
            );
            $rootsByGroup[$groupKey][] = $root;
        }

        $relinked = 0;

        foreach ($orphanedEventIds as $eventId) {
            $event = DB::table('payment_schedule_payment_events')->where('id', $eventId)->first();
            if ($event === null) {
                continue;
            }

            $groupKey = $this->rootGroupKey(
                (string) $event->party,
                isset($event->contractor_id) ? (int) $event->contractor_id : null,
            );

            $candidates = $rootsByGroup[$groupKey] ?? [];
            if ($candidates === []) {
                continue;
            }

            $targetRoot = $this->pickRootForEvent($candidates, (float) ($event->amount ?? 0));
            if ($targetRoot === null) {
                continue;
            }

            DB::table('payment_schedule_payment_events')
                ->where('id', $eventId)
                ->update([
                    'payment_schedule_id' => (int) $targetRoot->id,
                    'updated_at' => now(),
                ]);

            $relinked++;
        }

        $relinked += $this->relinkManagementAllocationsForOrder($orderId);

        return $relinked;
    }

    /**
     * Восстановить allocation_payment_schedule_id у разнесённых строк выписки после пересборки графика.
     */
    public function relinkManagementAllocationsForOrder(int $orderId): int
    {
        if (! Schema::hasTable('management_statement_lines')) {
            return 0;
        }

        $updated = 0;

        $lines = DB::table('management_statement_lines')
            ->where('allocation_order_id', $orderId)
            ->where('status', 'allocated')
            ->whereIn('match_type', ['operational', 'operational_split'])
            ->get(['id', 'match_type', 'allocation_payment_schedule_id']);

        foreach ($lines as $line) {
            if ($this->scheduleIdExists((int) ($line->allocation_payment_schedule_id ?? 0))) {
                continue;
            }

            if ((string) $line->match_type === 'operational_split' && Schema::hasTable('management_statement_line_splits')) {
                $updated += $this->relinkManagementSplitsForLine((int) $line->id);
            }

            $rootScheduleId = $this->resolveRootScheduleIdFromMgmtReference('mgmt:'.(int) $line->id);
            if ($rootScheduleId === null) {
                continue;
            }

            DB::table('management_statement_lines')
                ->where('id', (int) $line->id)
                ->update([
                    'allocation_payment_schedule_id' => $rootScheduleId,
                    'allocation_order_id' => $orderId,
                    'updated_at' => now(),
                ]);

            $updated++;
        }

        return $updated;
    }

    private function relinkManagementSplitsForLine(int $lineId): int
    {
        $updated = 0;

        $splits = DB::table('management_statement_line_splits')
            ->where('management_statement_line_id', $lineId)
            ->get(['id', 'payment_schedule_id']);

        foreach ($splits as $split) {
            if ($this->scheduleIdExists((int) ($split->payment_schedule_id ?? 0))) {
                continue;
            }

            $rootScheduleId = $this->resolveRootScheduleIdFromMgmtReference('mgmt:'.$lineId.':'.(int) $split->id);
            if ($rootScheduleId === null) {
                continue;
            }

            DB::table('management_statement_line_splits')
                ->where('id', (int) $split->id)
                ->update([
                    'payment_schedule_id' => $rootScheduleId,
                    'order_id' => DB::table('payment_schedules')->where('id', $rootScheduleId)->value('order_id'),
                    'updated_at' => now(),
                ]);

            $updated++;
        }

        return $updated;
    }

    private function resolveRootScheduleIdFromMgmtReference(string $reference): ?int
    {
        if (! $this->ledgerTableExists()) {
            return null;
        }

        $query = DB::table('payment_schedule_payment_events')
            ->where('transaction_reference', $reference);

        if (Schema::hasColumn('payment_schedule_payment_events', 'reversed_at')) {
            $query->whereNull('reversed_at');
        }

        $scheduleId = $query->value('payment_schedule_id');
        if ($scheduleId === null) {
            return null;
        }

        return $this->rootScheduleIdForLedgerScheduleId((int) $scheduleId);
    }

    private function rootScheduleIdForLedgerScheduleId(int $scheduleId): ?int
    {
        if (! $this->scheduleIdExists($scheduleId)) {
            return null;
        }

        $row = DB::table('payment_schedules')->where('id', $scheduleId)->first(['id', 'parent_payment_id', 'is_partial']);
        if ($row === null) {
            return null;
        }

        if (Schema::hasColumn('payment_schedules', 'is_partial')
            && Schema::hasColumn('payment_schedules', 'parent_payment_id')
            && (bool) ($row->is_partial ?? false)
            && $row->parent_payment_id !== null) {
            return (int) $row->parent_payment_id;
        }

        return (int) $row->id;
    }

    private function scheduleIdExists(int $scheduleId): bool
    {
        if ($scheduleId <= 0 || ! Schema::hasTable('payment_schedules')) {
            return false;
        }

        return DB::table('payment_schedules')->where('id', $scheduleId)->exists();
    }

    /**
     * @return list<int>
     */
    private function orphanedEventIdsForOrder(int $orderId): array
    {
        $query = DB::table('payment_schedule_payment_events')
            ->where('order_id', $orderId)
            ->where(function ($q): void {
                $q->whereNull('payment_schedule_id')
                    ->orWhereNotExists(function ($sub): void {
                        $sub->select(DB::raw(1))
                            ->from('payment_schedules')
                            ->whereColumn('payment_schedules.id', 'payment_schedule_payment_events.payment_schedule_id');
                    });
            });

        if (Schema::hasColumn('payment_schedule_payment_events', 'reversed_at')) {
            $query->whereNull('reversed_at');
        }

        return $query
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<object{
     *     id: int,
     *     party: string,
     *     amount: mixed,
     *     counterparty_id?: mixed,
     *     installment_sequence?: mixed,
     *     paid_amount?: mixed,
     *     remaining_amount?: mixed,
     * }>
     */
    private function rootSchedulesForOrder(int $orderId): array
    {
        $columns = ['id', 'party', 'amount', 'counterparty_id'];
        foreach (['installment_sequence', 'paid_amount', 'remaining_amount'] as $column) {
            if (Schema::hasColumn('payment_schedules', $column)) {
                $columns[] = $column;
            }
        }

        $query = DB::table('payment_schedules')
            ->where('order_id', $orderId)
            ->where('status', '!=', 'cancelled');

        if (Schema::hasColumn('payment_schedules', 'parent_payment_id')) {
            $query->whereNull('parent_payment_id');
        }

        if (Schema::hasColumn('payment_schedules', 'is_partial')) {
            $query->where(function ($q): void {
                $q->whereNull('is_partial')->orWhere('is_partial', false);
            });
        }

        return $query
            ->orderBy('installment_sequence')
            ->orderBy('id')
            ->get($columns)
            ->all();
    }

    private function rootGroupKey(string $party, ?int $counterpartyId): string
    {
        return strtolower($party).'|'.($counterpartyId ?? 0);
    }

    /**
     * @return array{customer?: int|null, carrier?: int|null}
     */
    private function orderPartyContractorIds(int $orderId): array
    {
        if (! Schema::hasTable('orders')) {
            return [];
        }

        $row = DB::table('orders')->where('id', $orderId)->first(['customer_id', 'carrier_id']);
        if ($row === null) {
            return [];
        }

        return [
            'customer' => $row->customer_id ? (int) $row->customer_id : null,
            'carrier' => $row->carrier_id ? (int) $row->carrier_id : null,
        ];
    }

    /**
     * @param  array{customer?: int|null, carrier?: int|null}  $orderPartyContractorIds
     */
    private function resolveContractorIdForRoot(object $root, array $orderPartyContractorIds): ?int
    {
        if (isset($root->counterparty_id) && (int) $root->counterparty_id > 0) {
            return (int) $root->counterparty_id;
        }

        $party = strtolower((string) $root->party);

        return $orderPartyContractorIds[$party] ?? null;
    }

    /**
     * @param  list<object{id: int, amount: mixed, paid_amount?: mixed, remaining_amount?: mixed}>  $roots
     */
    private function pickRootForEvent(array $roots, float $eventAmount): ?object
    {
        $eventAmount = round($eventAmount, 2);
        if ($eventAmount <= 0) {
            return $roots[0] ?? null;
        }

        $bestExact = null;
        $bestCapacity = null;
        $maxCapacity = -1.0;

        foreach ($roots as $root) {
            $rootAmount = round((float) ($root->amount ?? 0), 2);
            $paidAmount = round((float) ($root->paid_amount ?? 0), 2);
            $remaining = Schema::hasColumn('payment_schedules', 'remaining_amount') && $root->remaining_amount !== null
                ? round((float) $root->remaining_amount, 2)
                : max(0, $rootAmount - $paidAmount);

            if (abs($rootAmount - $eventAmount) <= 0.02) {
                $bestExact = $root;
                break;
            }

            if ($remaining <= 0.009 && $paidAmount + 0.009 >= $rootAmount && $rootAmount > 0) {
                continue;
            }

            if ($remaining > $maxCapacity) {
                $maxCapacity = $remaining;
                $bestCapacity = $root;
            }
        }

        return $bestExact ?? $bestCapacity ?? ($roots[0] ?? null);
    }
}
