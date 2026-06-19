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
        $hasReversalColumns = Schema::hasColumn('payment_schedule_payment_events', 'reversed_at');

        if ($hasReversalColumns && $event->reversed_at !== null) {
            throw new InvalidArgumentException('Платёж уже отменён.');
        }

        return DB::transaction(function () use ($event, $actor, $reason, $hasReversalColumns): PaymentSchedulePaymentEvent {
            $this->restoreScheduleAfterReversal($event);

            if ($hasReversalColumns) {
                $event->reversed_at = now();

                if (Schema::hasColumn('payment_schedule_payment_events', 'reversed_by')) {
                    $event->reversed_by = $actor->id;
                }

                if ($reason !== null && trim($reason) !== '') {
                    $event->notes = trim(($event->notes ?? '')."\n[Отмена] ".trim($reason));
                }

                $event->save();
            } else {
                $event->delete();
            }

            if ($event->order_id !== null) {
                PaymentScheduleAutomaticStatus::refreshForOrder((int) $event->order_id);
            }

            return $event->exists ? $event->fresh() : $event;
        });
    }

    public function reverseByManagementLineId(
        int $lineId,
        User $actor,
        ?string $reason = null,
        ?int $fallbackScheduleId = null,
    ): ?PaymentSchedulePaymentEvent {
        $events = PaymentSchedulePaymentEvent::query()
            ->active()
            ->where(function ($query) use ($lineId): void {
                $query->where('transaction_reference', 'mgmt:'.$lineId)
                    ->orWhere('transaction_reference', 'like', 'mgmt:'.$lineId.':%');
            })
            ->get();

        if ($events->isNotEmpty()) {
            $last = null;

            foreach ($events as $event) {
                $last = $this->reverseEvent($event, $actor, $reason);
            }

            return $last;
        }

        $this->restoreScheduleByManagementLineReference($lineId, $fallbackScheduleId);

        return null;
    }

    private function restoreScheduleByManagementLineReference(int $lineId, ?int $fallbackScheduleId = null): void
    {
        if (! Schema::hasColumn('payment_schedules', 'transaction_reference')) {
            return;
        }

        $reference = 'mgmt:'.$lineId;
        $orderIds = [];

        $partialSchedules = PaymentSchedule::query()
            ->where('transaction_reference', $reference)
            ->where('is_partial', true)
            ->get();

        foreach ($partialSchedules as $partial) {
            $this->restoreScheduleAfterReversal(new PaymentSchedulePaymentEvent([
                'payment_schedule_id' => $partial->id,
                'amount' => $partial->amount,
                'order_id' => $partial->order_id,
            ]));

            if ($partial->order_id !== null) {
                $orderIds[] = (int) $partial->order_id;
            }
        }

        $schedule = PaymentSchedule::query()
            ->where('transaction_reference', $reference)
            ->first();

        if ($schedule !== null) {
            $this->restoreScheduleAfterReversal(new PaymentSchedulePaymentEvent([
                'payment_schedule_id' => $schedule->id,
                'amount' => (float) ($schedule->paid_amount ?? $schedule->amount),
                'order_id' => $schedule->order_id,
            ]));

            if ($schedule->order_id !== null) {
                $orderIds[] = (int) $schedule->order_id;
            }
        } elseif ($fallbackScheduleId !== null) {
            $fallbackSchedule = PaymentSchedule::query()->find($fallbackScheduleId);

            if ($fallbackSchedule !== null && (float) ($fallbackSchedule->paid_amount ?? 0) > 0.009) {
                $this->restoreScheduleAfterReversal(new PaymentSchedulePaymentEvent([
                    'payment_schedule_id' => $fallbackSchedule->id,
                    'amount' => (float) ($fallbackSchedule->paid_amount ?? $fallbackSchedule->amount),
                    'order_id' => $fallbackSchedule->order_id,
                ]));

                if ($fallbackSchedule->order_id !== null) {
                    $orderIds[] = (int) $fallbackSchedule->order_id;
                }
            }
        }

        foreach (array_unique($orderIds) as $orderId) {
            PaymentScheduleAutomaticStatus::refreshForOrder($orderId);
        }
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
