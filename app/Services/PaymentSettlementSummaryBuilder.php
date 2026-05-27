<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contractor;
use App\Models\Order;
use App\Models\PaymentSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Сводка фактических оплат по графику для вкладки «Основное» мастера заказа.
 */
class PaymentSettlementSummaryBuilder
{
    /**
     * @return array{
     *     lines: list<array{
     *         key: string,
     *         party: string,
     *         counterparty_id: ?int,
     *         counterparty_name: string,
     *         state: string,
     *         percent_paid: float,
     *         last_payment_at: ?string,
     *         has_rows: bool,
     *     }>
     * }
     */
    public function forOrder(Order $order): array
    {
        if (! Schema::hasTable('payment_schedules')) {
            return ['lines' => []];
        }

        $order->loadMissing('client');

        $roots = $this->rootPaymentSchedules((int) $order->id);

        if ($roots->isEmpty()) {
            return ['lines' => []];
        }

        $lines = [];

        $customerRoots = $roots->where('party', 'customer');
        if ($customerRoots->isNotEmpty()) {
            $lines[] = $this->buildLine(
                party: 'customer',
                counterpartyId: null,
                counterpartyName: (string) ($order->client?->name ?? 'Клиент'),
                roots: $customerRoots,
            );
        }

        $carrierRoots = $roots->where('party', 'carrier');
        $carrierNames = $this->carrierNamesById($carrierRoots, $order);

        foreach ($carrierRoots->groupBy(fn (PaymentSchedule $row): string => (string) ($row->counterparty_id ?? 'none')) as $groupKey => $groupRows) {
            $counterpartyId = $groupKey === 'none' ? null : (int) $groupKey;
            $lines[] = $this->buildLine(
                party: 'carrier',
                counterpartyId: $counterpartyId,
                counterpartyName: $carrierNames[$counterpartyId] ?? ($counterpartyId !== null ? "Перевозчик #{$counterpartyId}" : 'Перевозчик'),
                roots: $groupRows,
            );
        }

        return ['lines' => $lines];
    }

    /**
     * @return Collection<int, PaymentSchedule>
     */
    private function rootPaymentSchedules(int $orderId): Collection
    {
        $query = PaymentSchedule::query()
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

        return $query->get();
    }

    /**
     * @param  Collection<int, PaymentSchedule>  $roots
     * @return array<string, string>
     */
    private function carrierNamesById(Collection $roots, Order $order): array
    {
        $ids = $roots
            ->pluck('counterparty_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        return Contractor::query()
            ->whereIn('id', $ids)
            ->pluck('name', 'id')
            ->map(fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * @param  Collection<int, PaymentSchedule>  $roots
     * @return array{
     *     key: string,
     *     party: string,
     *     counterparty_id: ?int,
     *     counterparty_name: string,
     *     state: string,
     *     percent_paid: float,
     *     last_payment_at: ?string,
     *     has_rows: bool,
     * }
     */
    private function buildLine(
        string $party,
        ?int $counterpartyId,
        string $counterpartyName,
        Collection $roots,
    ): array {
        $scheduledTotal = 0.0;
        $paidTotal = 0.0;
        $allRootsSettled = true;
        $maxPaymentDate = null;

        foreach ($roots as $row) {
            $scheduledTotal += (float) $row->amount;
            $paidTotal += $this->paidAmountForRoot($row);

            if (! $this->rootRowIsFullySettled($row)) {
                $allRootsSettled = false;
            }

            $rowLatest = $this->latestPaymentDate($row);
            if ($rowLatest !== null && ($maxPaymentDate === null || $rowLatest->gt($maxPaymentDate))) {
                $maxPaymentDate = $rowLatest;
            }
        }

        $percentPaid = $scheduledTotal > 0
            ? round(min(100, ($paidTotal / $scheduledTotal) * 100), 1)
            : 0.0;

        $isComplete = $allRootsSettled
            && $scheduledTotal > 0
            && $paidTotal + 0.009 >= $scheduledTotal;

        $state = match (true) {
            $isComplete => 'complete',
            $paidTotal > 0.009 => 'partial',
            default => 'none',
        };

        return [
            'key' => $party.'|'.($counterpartyId ?? '0'),
            'party' => $party,
            'counterparty_id' => $counterpartyId,
            'counterparty_name' => $counterpartyName,
            'state' => $state,
            'percent_paid' => $percentPaid,
            'last_payment_at' => $maxPaymentDate?->toDateString(),
            'has_rows' => true,
        ];
    }

    /**
     * @return array{scheduled: float, paid: float, complete: bool, state: string, percent_paid: float}
     */
    public static function aggregateFromAmounts(float $scheduledTotal, float $paidTotal, bool $allRootsSettled): array
    {
        $percentPaid = $scheduledTotal > 0
            ? round(min(100, ($paidTotal / $scheduledTotal) * 100), 1)
            : 0.0;

        $isComplete = $allRootsSettled
            && $scheduledTotal > 0
            && $paidTotal + 0.009 >= $scheduledTotal;

        $state = match (true) {
            $isComplete => 'complete',
            $paidTotal > 0.009 => 'partial',
            default => 'none',
        };

        return [
            'scheduled' => $scheduledTotal,
            'paid' => $paidTotal,
            'complete' => $isComplete,
            'state' => $state,
            'percent_paid' => $percentPaid,
        ];
    }

    private function paidAmountForRoot(PaymentSchedule $row): float
    {
        $paid = (float) ($row->paid_amount ?? 0);
        if ($paid > 0) {
            return min($paid, (float) $row->amount);
        }

        if ($row->status === 'paid') {
            return (float) $row->amount;
        }

        return 0.0;
    }

    private function rootRowIsFullySettled(PaymentSchedule $row): bool
    {
        if ($row->status === 'paid') {
            return true;
        }

        if (Schema::hasColumn('payment_schedules', 'remaining_amount') && $row->remaining_amount !== null) {
            return (float) $row->remaining_amount <= 0;
        }

        return false;
    }

    private function latestPaymentDate(PaymentSchedule $row): ?Carbon
    {
        $dates = collect();

        if ($row->actual_date !== null) {
            $dates->push(Carbon::parse($row->actual_date));
        }

        if (! Schema::hasColumn('payment_schedules', 'parent_payment_id')) {
            /** @var Carbon|null $max */
            $max = $dates->max();

            return $max;
        }

        $partialQuery = PaymentSchedule::query()->where('parent_payment_id', $row->id);

        if (Schema::hasColumn('payment_schedules', 'is_partial')) {
            $partialQuery->where('is_partial', true);
        }

        foreach ($partialQuery->get(['actual_date']) as $child) {
            if ($child->actual_date !== null) {
                $dates->push(Carbon::parse($child->actual_date));
            }
        }

        /** @var Carbon|null $max */
        $max = $dates->max();

        return $max;
    }
}
