<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderCompensationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SyncPaymentSchedulesFromTermsCommand extends Command
{
    protected $signature = 'payment-schedules:sync-from-terms
                            {--order= : Пересобрать график только для указанного заказа}';

    protected $description = 'Пересобрать строки графика оплат из условий заказа (в т.ч. несколько платежей заказчика)';

    public function handle(OrderCompensationService $compensationService): int
    {
        if (! Schema::hasTable('payment_schedules')) {
            $this->error('Таблица payment_schedules не найдена.');

            return self::FAILURE;
        }

        $orderId = $this->option('order');
        $query = Order::query()->orderBy('id');

        if ($orderId !== null && $orderId !== '') {
            $query->whereKey((int) $orderId);
        }

        $count = 0;

        $query->chunkById(100, function ($orders) use ($compensationService, &$count): void {
            foreach ($orders as $order) {
                $compensationService->resyncPaymentSchedulesForOrder($order);
                $count++;
            }
        });

        $this->info("График оплат пересобран для заказов: {$count}.");

        return self::SUCCESS;
    }
}
