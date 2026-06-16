<?php

namespace App\Services\Finance;

use App\Models\PaymentSchedule;
use App\Models\PaymentSchedulePaymentEvent;
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

        if ($schedule->status !== 'paid') {
            $schedule->status = 'pending';
        }

        $schedule->save();

        if ($schedule->order_id !== null) {
            PaymentScheduleAutomaticStatus::refreshForOrder((int) $schedule->order_id);
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
}
