<?php

namespace App\Console\Commands;

use App\Services\SalaryPayrollService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

#[Signature('salary:clean-start
    {--date=2026-08-16 : Дата чистого старта (периоды с period_end строго раньше закрываются)}
    {--dry-run : Только отчёт, без записи}
    {--force : Подтверждение записи (обязательно без --dry-run)}')]
#[Description('Чистый старт ЗП: закрыть периоды и хвосты начислений/авансов до указанной даты')]
class SalaryCleanStartCommand extends Command
{
    public function handle(SalaryPayrollService $salaryPayrollService): int
    {
        if (! Schema::hasTable('salary_periods') || ! Schema::hasTable('salary_accruals')) {
            $this->error('Нет таблиц salary_periods / salary_accruals.');

            return self::FAILURE;
        }

        $date = (string) $this->option('date');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (! $dryRun && ! $force) {
            $this->error('Для записи укажите --force (или сначала --dry-run).');

            return self::FAILURE;
        }

        try {
            $report = $salaryPayrollService->applySalaryCleanStart($date, null, $dryRun);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(($dryRun ? '[dry-run] ' : '').'Чистый старт ЗП с '.$report['clean_start_date']);
        $this->line('Периодов к закрытию (не closed): '.$report['periods_closed']);
        $this->line('Периодов затронуто: '.count($report['periods_touched']));
        foreach ($report['periods_touched'] as $period) {
            $this->line(sprintf(
                '  #%d %s..%s was=%s',
                $period['id'],
                $period['period_start'],
                $period['period_end'],
                $period['status_before'],
            ));
        }
        $this->line('Начислений закрыто: '.$report['accruals_settled']);
        $this->line('Сумма хвоста (write-off): '.number_format($report['unpaid_closed_sum'], 2, '.', ' ').' ₽');
        $this->line('Служебных выплат: '.$report['writeoff_payouts']);
        $this->line('Авансов ужато: '.$report['advances_clamped'].' на '.number_format($report['advance_clamped_sum'], 2, '.', ' ').' ₽');
        $this->line('Заказов затронуто: '.count($report['order_ids']));

        if ($dryRun) {
            $this->warn('dry-run: изменений в БД нет.');
        } else {
            $this->info('Готово: периоды закрыты, хвосты зафиксированы выплатами на '.$report['clean_start_date'].'.');
        }

        return self::SUCCESS;
    }
}
