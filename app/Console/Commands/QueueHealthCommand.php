<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QueueHealthCommand extends Command
{
    protected $signature = 'queue:health';

    protected $description = 'Краткая диагностика очереди: pending/failed jobs и подсказки по worker';

    public function handle(): int
    {
        $connection = (string) config('queue.default');
        $this->line('QUEUE_CONNECTION: '.$connection);

        if ($connection === 'sync') {
            $this->warn('Очередь в режиме sync — jobs выполняются inline, отдельный worker не нужен.');

            return self::SUCCESS;
        }

        $pending = $this->pendingJobsCount();
        $failed = $this->failedJobsCount();

        $this->line('Pending jobs: '.$pending);
        $this->line('Failed jobs (total): '.$failed);

        if ($pending > 0) {
            $this->warn('Есть необработанные jobs — убедитесь, что запущен worker:');
            $this->line('  php artisan queue:work --queue='.config('async.queues.mail', 'mail').','.config('async.queues.default', 'default'));
        }

        if ($failed > 0) {
            $this->warn('Есть failed jobs — просмотр: php artisan queue:failed');
        }

        if ((bool) config('async.outbound_mail')) {
            $this->info('ASYNC_OUTBOUND_MAIL=true — исходящая почта уходит через очередь «'.config('async.queues.mail', 'mail').'».');
        } else {
            $this->line('ASYNC_OUTBOUND_MAIL=false — исходящая почта отправляется синхронно (по умолчанию).');
        }

        return self::SUCCESS;
    }

    private function pendingJobsCount(): int
    {
        if (! Schema::hasTable('jobs')) {
            return 0;
        }

        return (int) DB::table('jobs')->count();
    }

    private function failedJobsCount(): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return (int) DB::table('failed_jobs')->count();
    }
}
