<?php

namespace App\Console\Commands;

use App\Models\PaymentSchedule;
use App\Models\PaymentSchedulePaymentEvent;
use App\Services\Finance\PaymentSchedulePaymentLedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BackfillPaymentSchedulePaymentEventsCommand extends Command
{
    protected $signature = 'payment-schedules:backfill-payment-events {--fresh : Очистить журнал и заполнить заново}';

    protected $description = 'Заполнить журнал фактических оплат из строк графика и частичных платежей';

    public function handle(PaymentSchedulePaymentLedgerService $ledger): int
    {
        if (! $ledger->ledgerTableExists()) {
            $this->error('Таблица payment_schedule_payment_events не найдена. Выполните миграции.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            PaymentSchedulePaymentEvent::query()->delete();
            $this->warn('Журнал очищен.');
        }

        if (! Schema::hasTable('payment_schedules') || ! Schema::hasColumn('payment_schedules', 'paid_amount')) {
            $this->error('Таблица payment_schedules не готова.');

            return self::FAILURE;
        }

        $created = 0;

        $partialQuery = PaymentSchedule::query()->where('paid_amount', '>', 0);

        if (Schema::hasColumn('payment_schedules', 'is_partial')) {
            $partialQuery->where('is_partial', true);
        } else {
            $partialQuery->whereRaw('1 = 0');
        }

        foreach ($partialQuery->cursor() as $partial) {
            if ($this->eventExistsForSchedule((int) $partial->id)) {
                continue;
            }

            $ledger->recordFromPaymentSchedule(
                $partial,
                (float) $partial->paid_amount,
                optional($partial->actual_date)?->toDateString() ?? optional($partial->planned_date)?->toDateString() ?? now()->toDateString(),
                [
                    'payment_method' => $partial->payment_method,
                    'transaction_reference' => $partial->transaction_reference,
                    'notes' => $partial->notes,
                ],
                null,
                (int) $partial->id,
            );

            $created++;
        }

        $parentQuery = PaymentSchedule::query()
            ->where(function ($query): void {
                $query->whereNull('is_partial')->orWhere('is_partial', false);
            })
            ->where('paid_amount', '>', 0);

        foreach ($parentQuery->cursor() as $parent) {
            $partialSum = $parent->partialPayments()->sum('paid_amount');
            $parentPaid = max(0, (float) $parent->paid_amount - (float) $partialSum);

            if ($parentPaid <= 0 || $this->eventExistsForSchedule((int) $parent->id)) {
                continue;
            }

            $ledger->recordFromPaymentSchedule(
                $parent,
                $parentPaid,
                optional($parent->actual_date)?->toDateString() ?? optional($parent->planned_date)?->toDateString() ?? now()->toDateString(),
                [
                    'payment_method' => $parent->payment_method,
                    'transaction_reference' => $parent->transaction_reference,
                    'notes' => $parent->notes,
                ],
                null,
            );

            $created++;
        }

        $this->info("Добавлено записей в журнал: {$created}");

        return self::SUCCESS;
    }

    private function eventExistsForSchedule(int $paymentScheduleId): bool
    {
        return PaymentSchedulePaymentEvent::query()
            ->where('payment_schedule_id', $paymentScheduleId)
            ->exists();
    }
}
