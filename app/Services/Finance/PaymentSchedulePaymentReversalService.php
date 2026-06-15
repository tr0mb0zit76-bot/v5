<?php

namespace App\Services\Finance;

use App\Models\PaymentSchedule;
use App\Models\PaymentSchedulePaymentEvent;
use App\Models\User;
use App\Support\PaymentScheduleAutomaticStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class PaymentSchedulePaymentReversalService
{
    public function reverseEvent(PaymentSchedulePaymentEvent $event, User $actor, ?string $reason = null): PaymentSchedulePaymentEvent
    {
        if ($event->reversed_at !== null) {
            throw new InvalidArgumentException('Платёж уже отменён.');
        }

        return DB::transaction(function () use ($event, $actor, $reason): PaymentSchedulePaymentEvent {
            $this->restoreScheduleAfterReversal($event);

            $event->reversed_at = now();
            $event->reversed_by = $actor->id;

            if ($reason !== null && trim($reason) !== '') {
                $event->notes = trim(($event->notes ?? '')."\n[Отмена] ".trim($reason));
            }

            $event->save();

            if ($event->order_id !== null) {
                PaymentScheduleAutomaticStatus::refreshForOrder((int) $event->order_id);
            }

            return $event->fresh();
        });
    }

    public function reverseByManagementLineId(int $lineId, User $actor, ?string $reason = null): ?PaymentSchedulePaymentEvent
    {
        $event = PaymentSchedulePaymentEvent::query()
            ->active()
            ->where('transaction_reference', 'mgmt:'.$lineId)
            ->first();

        if ($event === null) {
            return null;
        }

        return $this->reverseEvent($event, $actor, $reason);
    }

    private function restoreScheduleAfterReversal(PaymentSchedulePaymentEvent $event): void
    {
        if (! Schema::hasColumn('payment_schedules', 'paid_amount') || $event->payment_schedule_id === null) {
            return;
        }

        $schedule = PaymentSchedule::query()->find($event->payment_schedule_id);

        if ($schedule === null) {
            return;
        }

        $amount = (float) $event->amount;

        if (Schema::hasColumn('payment_schedules', 'is_partial') && $schedule->is_partial) {
            $parent = PaymentSchedule::query()->find($schedule->parent_payment_id);

            if ($parent !== null) {
                $parent->paid_amount = max(0, round((float) $parent->paid_amount - $amount, 2));
                $parent->remaining_amount = max(0, round((float) $parent->amount - (float) $parent->paid_amount, 2));
                $parent->status = $parent->remaining_amount <= 0.009 ? 'paid' : 'pending';
                $parent->save();
            }

            $schedule->delete();

            return;
        }

        $schedule->paid_amount = max(0, round((float) $schedule->paid_amount - $amount, 2));
        $schedule->remaining_amount = max(0, round((float) $schedule->amount - (float) $schedule->paid_amount, 2));

        if ($schedule->paid_amount <= 0.009) {
            $schedule->paid_amount = 0;
            $schedule->remaining_amount = (float) $schedule->amount;
            $schedule->actual_date = null;

            if (Schema::hasColumn('payment_schedules', 'payment_method')) {
                $schedule->payment_method = null;
            }

            if (Schema::hasColumn('payment_schedules', 'transaction_reference')) {
                $schedule->transaction_reference = null;
            }

            $schedule->status = 'pending';
        } else {
            $schedule->status = $schedule->remaining_amount <= 0.009 ? 'paid' : 'pending';
        }

        $schedule->save();
    }
}
