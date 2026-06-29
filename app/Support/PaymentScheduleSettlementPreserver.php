<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Сохраняет фактические оплаты по графику при пересборке payment_schedules из условий заказа.
 */
final class PaymentScheduleSettlementPreserver
{
    /**
     * @return array{
     *     roots: array<string, array{
     *         paid_amount: float,
     *         remaining_amount: float,
     *         status: string,
     *         actual_date: ?string,
     *         payment_method: ?string,
     *         transaction_reference: ?string,
     *         notes: ?string,
     *         payment_run_date: ?string,
     *         payment_run_by: ?int,
     *         payment_run_note: ?string,
     *         partials: list<array{
     *             amount: float,
     *             paid_amount: float,
     *             actual_date: ?string,
     *             payment_method: ?string,
     *             transaction_reference: ?string,
     *             notes: ?string,
     *         }>,
     *     }>
     * }
     */
    public function snapshot(int $orderId): array
    {
        if (! Schema::hasTable('payment_schedules')) {
            return ['roots' => []];
        }

        $columns = ['id', 'party', 'type', 'planned_date', 'amount', 'status'];
        foreach (['counterparty_id', 'installment_sequence', 'paid_amount', 'remaining_amount', 'actual_date', 'payment_method', 'transaction_reference', 'notes', 'parent_payment_id', 'is_partial', 'payment_run_date', 'payment_run_by', 'payment_run_note'] as $column) {
            if (Schema::hasColumn('payment_schedules', $column)) {
                $columns[] = $column;
            }
        }

        $rows = DB::table('payment_schedules')
            ->where('order_id', $orderId)
            ->get($columns);

        $rootsById = [];
        $snapshot = ['roots' => []];

        foreach ($rows as $row) {
            if ($this->isPartialRow($row)) {
                continue;
            }

            $rootsById[(int) $row->id] = $row;
            $key = $this->matchKey(
                (string) $row->party,
                (string) $row->type,
                $row->planned_date !== null ? (string) $row->planned_date : null,
                isset($row->counterparty_id) ? (int) $row->counterparty_id : null,
                isset($row->installment_sequence) ? (int) $row->installment_sequence : null,
            );

            if (! $this->rowHasSettlement((object) $row)) {
                continue;
            }

            $snapshot['roots'][$key] = $this->rootSettlementFromRow($row);
            $snapshot['roots'][$key]['partials'] = [];
        }

        foreach ($rows as $row) {
            if (! $this->isPartialRow($row)) {
                continue;
            }

            $parentId = (int) ($row->parent_payment_id ?? 0);
            $parent = $rootsById[$parentId] ?? null;
            if ($parent === null) {
                continue;
            }

            $key = $this->matchKey(
                (string) $parent->party,
                (string) $parent->type,
                $parent->planned_date !== null ? (string) $parent->planned_date : null,
                isset($parent->counterparty_id) ? (int) $parent->counterparty_id : null,
                isset($parent->installment_sequence) ? (int) $parent->installment_sequence : null,
            );

            if (! isset($snapshot['roots'][$key])) {
                continue;
            }

            $snapshot['roots'][$key]['partials'][] = [
                'amount' => (float) ($row->amount ?? 0),
                'paid_amount' => (float) ($row->paid_amount ?? $row->amount ?? 0),
                'actual_date' => $row->actual_date !== null ? (string) $row->actual_date : null,
                'payment_method' => isset($row->payment_method) ? (string) $row->payment_method : null,
                'transaction_reference' => isset($row->transaction_reference) ? (string) $row->transaction_reference : null,
                'notes' => isset($row->notes) ? (string) $row->notes : null,
            ];
        }

        return $snapshot;
    }

    /**
     * @param  array{
     *     roots: array<string, array{
     *         paid_amount: float,
     *         remaining_amount: float,
     *         status: string,
     *         actual_date: ?string,
     *         payment_method: ?string,
     *         transaction_reference: ?string,
     *         notes: ?string,
     *         payment_run_date: ?string,
     *         payment_run_by: ?int,
     *         payment_run_note: ?string,
     *         partials: list<array<string, mixed>>,
     *     }>
     * }  $snapshot
     */
    public function restore(int $orderId, array $snapshot): void
    {
        if ($snapshot['roots'] === [] || ! Schema::hasTable('payment_schedules')) {
            return;
        }

        $query = DB::table('payment_schedules')->where('order_id', $orderId);
        if (Schema::hasColumn('payment_schedules', 'parent_payment_id')) {
            $query->whereNull('parent_payment_id');
        }
        if (Schema::hasColumn('payment_schedules', 'is_partial')) {
            $query->where(function ($q): void {
                $q->whereNull('is_partial')->orWhere('is_partial', false);
            });
        }

        $rootColumns = ['id', 'party', 'type', 'planned_date', 'amount', 'counterparty_id'];
        if (Schema::hasColumn('payment_schedules', 'installment_sequence')) {
            $rootColumns[] = 'installment_sequence';
        }

        $roots = $query->get($rootColumns);

        foreach ($roots as $root) {
            $sequence = isset($root->installment_sequence) ? (int) $root->installment_sequence : null;
            $key = $this->matchKey(
                (string) $root->party,
                (string) $root->type,
                $root->planned_date !== null ? (string) $root->planned_date : null,
                isset($root->counterparty_id) ? (int) $root->counterparty_id : null,
                $sequence,
            );

            $saved = $snapshot['roots'][$key]
                ?? $snapshot['roots'][$this->legacyMatchKey(
                    (string) $root->party,
                    (string) $root->type,
                    $root->planned_date !== null ? (string) $root->planned_date : null,
                    isset($root->counterparty_id) ? (int) $root->counterparty_id : null,
                )] ?? null;
            if ($saved === null) {
                continue;
            }

            $newAmount = (float) ($root->amount ?? 0);
            $paidAmount = min((float) $saved['paid_amount'], $newAmount);
            $remainingAmount = max(0, round($newAmount - $paidAmount, 2));
            $status = $saved['status'];
            if ($remainingAmount <= 0.009 && $newAmount > 0) {
                $status = 'paid';
                $remainingAmount = 0.0;
                $paidAmount = $newAmount;
            } elseif ($paidAmount > 0.009 && $status === 'pending') {
                $status = 'pending';
            }

            $update = [
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('payment_schedules', 'paid_amount')) {
                $update['paid_amount'] = $paidAmount;
            }
            if (Schema::hasColumn('payment_schedules', 'remaining_amount')) {
                $update['remaining_amount'] = $remainingAmount;
            }

            $update['status'] = $status;

            if (Schema::hasColumn('payment_schedules', 'actual_date')) {
                $update['actual_date'] = $saved['actual_date'];
            }
            if (Schema::hasColumn('payment_schedules', 'payment_method') && $saved['payment_method'] !== null) {
                $update['payment_method'] = $saved['payment_method'];
            }
            if (Schema::hasColumn('payment_schedules', 'transaction_reference') && $saved['transaction_reference'] !== null) {
                $update['transaction_reference'] = $saved['transaction_reference'];
            }
            if (Schema::hasColumn('payment_schedules', 'notes') && filled($saved['notes'] ?? null)) {
                $update['notes'] = $saved['notes'];
            }
            if (Schema::hasColumn('payment_schedules', 'payment_run_date')) {
                $update['payment_run_date'] = $saved['payment_run_date'] ?? null;
            }
            if (Schema::hasColumn('payment_schedules', 'payment_run_by')) {
                $update['payment_run_by'] = $saved['payment_run_by'] ?? null;
            }
            if (Schema::hasColumn('payment_schedules', 'payment_run_note')) {
                $update['payment_run_note'] = $saved['payment_run_note'] ?? null;
            }

            DB::table('payment_schedules')
                ->where('id', (int) $root->id)
                ->update($update);

            $this->restorePartialPayments((int) $root->id, $orderId, (string) $root->party, (string) $root->type, $saved['partials'] ?? []);
        }
    }

    public function matchKey(string $party, string $type, ?string $plannedDate, ?int $counterpartyId, ?int $installmentSequence = null): string
    {
        return strtolower($party).'|'.$type.'|'.($plannedDate ?? '').'|'.($counterpartyId ?? 0).'|'.($installmentSequence ?? 0);
    }

    public function legacyMatchKey(string $party, string $type, ?string $plannedDate, ?int $counterpartyId): string
    {
        return strtolower($party).'|'.$type.'|'.($plannedDate ?? '').'|'.($counterpartyId ?? 0);
    }

    /**
     * @param  list<array<string, mixed>>  $partials
     */
    private function restorePartialPayments(
        int $parentId,
        int $orderId,
        string $party,
        string $type,
        array $partials,
    ): void {
        if ($partials === []
            || ! Schema::hasColumn('payment_schedules', 'parent_payment_id')
            || ! Schema::hasColumn('payment_schedules', 'is_partial')) {
            return;
        }

        DB::table('payment_schedules')
            ->where('parent_payment_id', $parentId)
            ->where('is_partial', true)
            ->delete();

        foreach ($partials as $partial) {
            $amount = (float) ($partial['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $row = [
                'order_id' => $orderId,
                'party' => $party,
                'type' => $type,
                'amount' => $amount,
                'planned_date' => $partial['actual_date'] ?? null,
                'actual_date' => $partial['actual_date'] ?? null,
                'status' => 'paid',
                'parent_payment_id' => $parentId,
                'is_partial' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('payment_schedules', 'paid_amount')) {
                $row['paid_amount'] = (float) ($partial['paid_amount'] ?? $amount);
            }
            if (Schema::hasColumn('payment_schedules', 'remaining_amount')) {
                $row['remaining_amount'] = 0;
            }
            if (Schema::hasColumn('payment_schedules', 'payment_method')) {
                $row['payment_method'] = $partial['payment_method'] ?? null;
            }
            if (Schema::hasColumn('payment_schedules', 'transaction_reference')) {
                $row['transaction_reference'] = $partial['transaction_reference'] ?? null;
            }
            if (Schema::hasColumn('payment_schedules', 'notes')) {
                $row['notes'] = $partial['notes'] ?? 'Частичный платеж (восстановлен)';
            }

            DB::table('payment_schedules')->insert($row);
        }
    }

    /**
     * @return array{
     *     paid_amount: float,
     *     remaining_amount: float,
     *     status: string,
     *     actual_date: ?string,
     *     payment_method: ?string,
     *     transaction_reference: ?string,
     *     notes: ?string,
     *     payment_run_date: ?string,
     *     payment_run_by: ?int,
     *     payment_run_note: ?string,
     *     partials: list<array<string, mixed>>,
     * }
     */
    private function rootSettlementFromRow(object $row): array
    {
        $amount = (float) ($row->amount ?? 0);
        $paidAmount = Schema::hasColumn('payment_schedules', 'paid_amount')
            ? (float) ($row->paid_amount ?? 0)
            : (($row->status ?? '') === 'paid' ? $amount : 0.0);

        $remainingAmount = Schema::hasColumn('payment_schedules', 'remaining_amount')
            ? (float) ($row->remaining_amount ?? max(0, $amount - $paidAmount))
            : max(0, $amount - $paidAmount);

        return [
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'status' => (string) ($row->status ?? 'pending'),
            'actual_date' => $row->actual_date !== null ? (string) $row->actual_date : null,
            'payment_method' => isset($row->payment_method) ? (string) $row->payment_method : null,
            'transaction_reference' => isset($row->transaction_reference) ? (string) $row->transaction_reference : null,
            'notes' => isset($row->notes) ? (string) $row->notes : null,
            'payment_run_date' => isset($row->payment_run_date) && $row->payment_run_date !== null ? (string) $row->payment_run_date : null,
            'payment_run_by' => isset($row->payment_run_by) && $row->payment_run_by !== null ? (int) $row->payment_run_by : null,
            'payment_run_note' => isset($row->payment_run_note) && $row->payment_run_note !== null ? (string) $row->payment_run_note : null,
            'partials' => [],
        ];
    }

    private function rowHasSettlement(object $row): bool
    {
        if (Schema::hasColumn('payment_schedules', 'paid_amount') && (float) ($row->paid_amount ?? 0) > 0.009) {
            return true;
        }

        if (($row->status ?? '') === 'paid') {
            return true;
        }

        if ($row->actual_date !== null && $row->actual_date !== '') {
            return true;
        }

        return isset($row->payment_run_date) && $row->payment_run_date !== null && $row->payment_run_date !== '';
    }

    private function isPartialRow(object $row): bool
    {
        return Schema::hasColumn('payment_schedules', 'is_partial')
            && (bool) ($row->is_partial ?? false);
    }
}
