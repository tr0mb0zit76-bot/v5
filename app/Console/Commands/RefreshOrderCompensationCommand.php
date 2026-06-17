<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderCompensationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RefreshOrderCompensationCommand extends Command
{
    protected $signature = 'orders:refresh-compensation
                            {order? : ID заказа; без аргумента — все активные заказы}
                            {--dry-run : Только показать текущие значения без записи}';

    protected $description = 'Пересчитать kpi_percent, delta и salary_accrued для заказа(ов)';

    public function handle(OrderCompensationService $orderCompensationService): int
    {
        $orderId = $this->argument('order');
        $dryRun = (bool) $this->option('dry-run');

        $query = Order::query()
            ->when(
                Schema::hasColumn('orders', 'deleted_at'),
                fn ($builder) => $builder->whereNull('deleted_at'),
            )
            ->orderBy('id');

        if ($orderId !== null) {
            $query->whereKey((int) $orderId);
        }

        $orders = $query->get();

        if ($orders->isEmpty()) {
            $this->warn('Заказы не найдены.');

            return self::FAILURE;
        }

        $rows = [];

        foreach ($orders as $order) {
            $before = [
                'kpi_percent' => $order->kpi_percent,
                'delta' => $order->delta,
                'salary_accrued' => $order->salary_accrued,
            ];

            if (! $dryRun) {
                $orderCompensationService->refreshOrderCompensationFields($order->fresh());
                $order->refresh();
            } else {
                $preview = $orderCompensationService->calculateOrder($order);
                $order->kpi_percent = $preview['kpi_percent'];
                $order->delta = $preview['delta'];
                $order->salary_accrued = $preview['salary_accrued'];
            }

            $rows[] = [
                $order->id,
                $order->order_number,
                $before['kpi_percent'],
                $order->kpi_percent,
                $before['delta'],
                $order->delta,
            ];
        }

        $this->table(
            ['ID', '№', 'KPI было', 'KPI стало', 'Маржа было', 'Маржа стало'],
            $rows,
        );

        if ($dryRun) {
            $this->comment('Dry-run: изменения не сохранены.');
        } else {
            $this->info('Пересчёт завершён: '.$orders->count().' заказ(ов).');
        }

        return self::SUCCESS;
    }
}
