<?php

namespace App\Services\Finance;

use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Models\PaymentSchedulePaymentEvent;
use App\Services\OrderStatusService;
use App\Support\PaymentScheduleAutomaticStatus;
use App\Support\PaymentScheduleSettlementStatus;
use Illuminate\Support\Facades\Schema;

/**
 * Пересчёт paid_amount / remaining_amount корневых строк графика по журналу оплат.
 */
final class PaymentScheduleSettlementSyncService
{
    public function ledgerTableExists(): bool
    {
        return Schema::hasTable('payment_schedule_payment_events');
    }

    public function syncRootSchedule(PaymentSchedule $schedule): bool
    {
        if (! Schema::hasColumn('payment_schedules', 'paid_amount')
            || ! Schema::hasColumn('payment_schedules', 'remaining_amount')) {
            return false;
        }

        if ((bool) ($schedule->is_partial ?? false)) {
            return false;
        }

        $scheduleIds = [(int) $schedule->id];
        if (Schema::hasColumn('payment_schedules', 'parent_payment_id')) {
            $partialIds = $schedule->partialPayments()->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $scheduleIds = array_merge($scheduleIds, $partialIds);
        }

        $totalPaid = $this->sumActiveEventsForSchedules($scheduleIds);
        $amount = round((float) $schedule->amount, 2);

        if ($totalPaid <= 0.009) {
            if ((float) ($schedule->paid_amount ?? 0) <= 0.009) {
                return false;
            }

            if ($this->orderHasOrphanedLedgerEvents((int) $schedule->order_id, (string) $schedule->party, $schedule)) {
                return false;
            }

            $schedule->paid_amount = 0;
            $schedule->remaining_amount = 0;
            $schedule->actual_date = null;
            $schedule->status = 'pending';

            if (Schema::hasColumn('payment_schedules', 'payment_method')) {
                $schedule->payment_method = null;
            }

            if (Schema::hasColumn('payment_schedules', 'transaction_reference')) {
                $schedule->transaction_reference = null;
            }

            $schedule->save();

            return true;
        }

        $schedule->paid_amount = $totalPaid;
        $schedule->remaining_amount = max(0, round($amount - $totalPaid, 2));
        PaymentScheduleSettlementStatus::applyToSchedule($schedule);

        $schedule->save();

        if ($schedule->order_id !== null) {
            PaymentScheduleAutomaticStatus::refreshForOrder((int) $schedule->order_id);
            $this->syncOrderDerivedStatus((int) $schedule->order_id);
        }

        return true;
    }

    /**
     * @return array{scanned: int, updated: int}
     */
    public function syncAllRootSchedules(): array
    {
        $scanned = 0;
        $updated = 0;

        $query = PaymentSchedule::query();

        if (Schema::hasColumn('payment_schedules', 'parent_payment_id')) {
            $query->whereNull('parent_payment_id');
        }

        if (Schema::hasColumn('payment_schedules', 'is_partial')) {
            $query->where(function ($q): void {
                $q->whereNull('is_partial')->orWhere('is_partial', false);
            });
        }

        foreach ($query->cursor() as $schedule) {
            $scanned++;

            if ($this->syncRootSchedule($schedule)) {
                $updated++;
            }
        }

        return [
            'scanned' => $scanned,
            'updated' => $updated,
        ];
    }

    /**
     * @param  list<int>  $scheduleIds
     */
    private function sumActiveEventsForSchedules(array $scheduleIds): float
    {
        if ($scheduleIds === [] || ! $this->ledgerTableExists()) {
            return 0.0;
        }

        $query = PaymentSchedulePaymentEvent::query()
            ->whereIn('payment_schedule_id', $scheduleIds);

        if (Schema::hasColumn('payment_schedule_payment_events', 'reversed_at')) {
            $query->whereNull('reversed_at');
        }

        return round((float) $query->sum('amount'), 2);
    }

    private function orderHasOrphanedLedgerEvents(int $orderId, string $party, PaymentSchedule $schedule): bool
    {
        if (! $this->ledgerTableExists()) {
            return false;
        }

        $query = PaymentSchedulePaymentEvent::query()
            ->where('order_id', $orderId)
            ->where('party', strtolower($party))
            ->where(function ($q): void {
                $q->whereNull('payment_schedule_id')
                    ->orWhereNotExists(function ($sub): void {
                        $sub->selectRaw('1')
                            ->from('payment_schedules')
                            ->whereColumn('payment_schedules.id', 'payment_schedule_payment_events.payment_schedule_id');
                    });
            });

        if (Schema::hasColumn('payment_schedule_payment_events', 'reversed_at')) {
            $query->whereNull('reversed_at');
        }

        $contractorId = $this->resolveContractorIdForSchedule($schedule, strtolower($party));
        if ($contractorId !== null) {
            $query->where('contractor_id', $contractorId);
        }

        return $query->exists();
    }

    private function resolveContractorIdForSchedule(PaymentSchedule $schedule, string $party): ?int
    {
        if ($party === 'customer' || $party === 'carrier' || $party === 'contractor') {
            if (Schema::hasColumn('payment_schedules', 'counterparty_id') && $schedule->counterparty_id) {
                return (int) $schedule->counterparty_id;
            }
        }

        $schedule->loadMissing('order:id,customer_id,carrier_id');

        return match ($party) {
            'customer' => $schedule->order?->customer_id ? (int) $schedule->order->customer_id : null,
            'carrier' => $schedule->order?->carrier_id ? (int) $schedule->order->carrier_id : null,
            default => null,
        };
    }

    private function syncOrderDerivedStatus(int $orderId): void
    {
        $order = Order::query()->find($orderId);

        if ($order === null) {
            return;
        }

        app(OrderStatusService::class)->syncStoredStatus($order);
    }
}
