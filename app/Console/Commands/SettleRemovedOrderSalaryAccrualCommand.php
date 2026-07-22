<?php

namespace App\Console\Commands;

use App\Services\SalaryPayrollService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('salary:settle-removed-order
    {orderId : Order id (including soft-deleted)}
    {--dry-run : Show unpaid accruals without writing}')]
#[Description('Закрыть зарплатное начисление по удалённому/вне-грида заказу (как EXWL-1)')]
class SettleRemovedOrderSalaryAccrualCommand extends Command
{
    public function handle(SalaryPayrollService $salaryPayrollService): int
    {
        $orderId = (int) $this->argument('orderId');
        if ($orderId <= 0) {
            $this->error('Укажите положительный orderId.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('orders') || ! Schema::hasTable('salary_accruals')) {
            $this->error('Нет таблиц orders / salary_accruals.');

            return self::FAILURE;
        }

        $orderQuery = DB::table('orders')->where('id', $orderId);
        $order = $orderQuery->first();
        if ($order === null) {
            $this->error("Заказ #{$orderId} не найден.");

            return self::FAILURE;
        }

        $accruals = DB::table('salary_accruals')
            ->where('order_id', $orderId)
            ->get(['id', 'salary_amount', 'paid_amount_fact', 'unpaid_amount']);

        $this->info(sprintf(
            'Заказ #%d (%s), deleted_at=%s, accruals=%d',
            $orderId,
            (string) ($order->order_number ?? '—'),
            (string) ($order->deleted_at ?? 'null'),
            $accruals->count(),
        ));

        foreach ($accruals as $accrual) {
            $this->line(sprintf(
                '  accrual #%d: salary=%s paid=%s unpaid=%s',
                (int) $accrual->id,
                (string) $accrual->salary_amount,
                (string) $accrual->paid_amount_fact,
                (string) $accrual->unpaid_amount,
            ));
        }

        if ((bool) $this->option('dry-run')) {
            $this->warn('dry-run: изменений нет.');

            return self::SUCCESS;
        }

        $salaryPayrollService->settleAccrualForRemovedOrder($orderId, true);
        $this->info('Начисления закрыты, заказ помечен closed / salary_paid синхронизирован.');

        return self::SUCCESS;
    }
}
