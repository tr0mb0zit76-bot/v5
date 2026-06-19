<?php

namespace App\Console\Commands;

use App\Services\Finance\PaymentScheduleSettlementSyncService;
use App\Support\PaymentSchedulePaymentEventRelinker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncPaymentScheduleSettlementAmountsCommand extends Command
{
    protected $signature = 'payment-schedules:sync-settlement-amounts';

    protected $description = 'Пересчитать paid_amount и remaining_amount корневых строк графика по журналу оплат';

    public function handle(
        PaymentSchedulePaymentEventRelinker $relinker,
        PaymentScheduleSettlementSyncService $sync,
    ): int {
        if (! $sync->ledgerTableExists()) {
            $this->error('Таблица payment_schedule_payment_events не найдена.');

            return self::FAILURE;
        }

        $orderIds = DB::table('payment_schedule_payment_events')
            ->where(function ($q): void {
                $q->whereNull('payment_schedule_id')
                    ->orWhereNotExists(function ($sub): void {
                        $sub->selectRaw('1')
                            ->from('payment_schedules')
                            ->whereColumn('payment_schedules.id', 'payment_schedule_payment_events.payment_schedule_id');
                    });
            })
            ->distinct()
            ->pluck('order_id');

        foreach ($orderIds as $orderId) {
            $relinker->relinkOrphanedEventsForOrder((int) $orderId);
        }

        $result = $sync->syncAllRootSchedules();

        $this->info(sprintf(
            'Проверено строк: %d, обновлено: %d',
            $result['scanned'],
            $result['updated'],
        ));

        return self::SUCCESS;
    }
}
