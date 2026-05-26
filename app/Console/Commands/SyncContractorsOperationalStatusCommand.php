<?php

namespace App\Console\Commands;

use App\Models\Contractor;
use App\Services\ContractorOperationalStatusService;
use Illuminate\Console\Command;

class SyncContractorsOperationalStatusCommand extends Command
{
    protected $signature = 'contractors:sync-operational-status {--chunk=200 : Размер пакета}';

    protected $description = 'Синхронизирует паузу в работе и срок действия проверки контрагентов';

    public function handle(ContractorOperationalStatusService $statusService): int
    {
        $chunkSize = max(50, (int) $this->option('chunk'));

        Contractor::query()
            ->orderBy('id')
            ->chunkById($chunkSize, function ($contractors) use ($statusService): void {
                $statusService->syncMany($contractors);
            });

        $this->info('Синхронизация операционных статусов контрагентов завершена.');

        return self::SUCCESS;
    }
}
