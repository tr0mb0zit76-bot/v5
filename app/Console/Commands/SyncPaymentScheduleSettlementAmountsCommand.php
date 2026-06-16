<?php

namespace App\Console\Commands;

use App\Services\Finance\PaymentScheduleSettlementSyncService;
use Illuminate\Console\Command;

class SyncPaymentScheduleSettlementAmountsCommand extends Command
{
    protected $signature = 'payment-schedules:sync-settlement-amounts';

    protected $description = 'Пересчитать paid_amount и remaining_amount корневых строк графика по журналу оплат';

    public function handle(PaymentScheduleSettlementSyncService $sync): int
    {
        if (! $sync->ledgerTableExists()) {
            $this->error('Таблица payment_schedule_payment_events не найдена.');

            return self::FAILURE;
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
