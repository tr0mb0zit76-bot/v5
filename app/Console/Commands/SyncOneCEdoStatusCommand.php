<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\OneC\OneCEdoStatusSyncService;
use Illuminate\Console\Command;

class SyncOneCEdoStatusCommand extends Command
{
    protected $signature = 'one-c:sync-edo-status
        {--limit= : Максимум реализаций за прогон}';

    protected $description = 'Подтянуть статус исходящего ЭДО (заказчику) из 1С в чек-лист closing / сроки оплаты';

    public function handle(OneCEdoStatusSyncService $sync): int
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
