<?php

namespace App\Console\Commands;

use App\Models\PaymentSchedule;
use App\Services\Finance\PaymentScheduleSettlementSyncService;
use App\Support\PaymentSchedulePaymentEventRelinker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RepairPaymentScheduleSettlementCommand extends Command
{
    protected $signature = 'payment-schedules:repair-settlement {--order= : ID заказа для точечного восстановления}';

    protected $description = 'Перепривязать журнал оплат к строкам графика и пересчитать paid_amount / remaining_amount';

    public function handle(
        PaymentSchedulePaymentEventRelinker $relinker,
        PaymentScheduleSettlementSyncService $sync,
    ): int {
        if (! $relinker->ledgerTableExists()) {
            $this->error('Таблица payment_schedule_payment_events не найдена.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('payment_schedules')) {
            $this->error('Таблица payment_schedules не найдена.');

            return self::FAILURE;
        }

        $orderId = $this->option('order');
        $orderIds = $orderId !== null && $orderId !== ''
            ? [(int) $orderId]
            : $this->orderIdsWithOrphanedEvents();

        if ($orderIds === []) {
            $this->info('Сиротских записей журнала не найдено.');

            return self::SUCCESS;
        }

        $relinkedTotal = 0;
        $updatedTotal = 0;

        foreach ($orderIds as $id) {
            $relinked = $relinker->relinkOrphanedEventsForOrder($id);
            $relinkedTotal += $relinked;

            $updated = $this->syncOrderRoots($sync, $id);
            $updatedTotal += $updated;

            if ($relinked > 0 || $updated > 0) {
                $this->line("Заказ #{$id}: перепривязано {$relinked}, обновлено строк {$updated}");
            }
        }

        $this->info("Итого: перепривязано {$relinkedTotal}, обновлено строк графика {$updatedTotal}");

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function orderIdsWithOrphanedEvents(): array
    {
        $query = DB::table('payment_schedule_payment_events')
            ->where(function ($q): void {
                $q->whereNull('payment_schedule_id')
                    ->orWhereNotExists(function ($sub): void {
                        $sub->select(DB::raw(1))
                            ->from('payment_schedules')
                            ->whereColumn('payment_schedules.id', 'payment_schedule_payment_events.payment_schedule_id');
                    });
            });

        if (Schema::hasColumn('payment_schedule_payment_events', 'reversed_at')) {
            $query->whereNull('reversed_at');
        }

        return $query
            ->distinct()
            ->orderBy('order_id')
            ->pluck('order_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    private function syncOrderRoots(PaymentScheduleSettlementSyncService $sync, int $orderId): int
    {
        $updated = 0;

        $query = PaymentSchedule::query()->where('order_id', $orderId);

        if (Schema::hasColumn('payment_schedules', 'parent_payment_id')) {
            $query->whereNull('parent_payment_id');
        }

        if (Schema::hasColumn('payment_schedules', 'is_partial')) {
            $query->where(function ($q): void {
                $q->whereNull('is_partial')->orWhere('is_partial', false);
            });
        }

        foreach ($query->cursor() as $schedule) {
            if ($sync->syncRootSchedule($schedule)) {
                $updated++;
            }
        }

        return $updated;
    }
}
