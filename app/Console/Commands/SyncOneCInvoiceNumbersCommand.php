<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\OneC\OneCInvoiceNumberSyncService;
use Illuminate\Console\Command;

class SyncOneCInvoiceNumbersCommand extends Command
{
    protected $signature = 'one-c:sync-invoice-numbers
        {--limit= : Максимум реализаций за прогон}';

    protected $description = 'Подтянуть номера счетов покупателя из 1С (реализация → счёт) в заказы и график';

    public function handle(OneCInvoiceNumberSyncService $sync): int
    {
        if (! (bool) config('one_c.enabled')) {
            $this->error('ONE_C_ENABLED=false');

            return self::FAILURE;
        }

        $limitOption = $this->option('limit');
        $limit = is_numeric($limitOption) ? (int) $limitOption : null;

        $stats = $sync->sync($limit);
        $this->line(json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
