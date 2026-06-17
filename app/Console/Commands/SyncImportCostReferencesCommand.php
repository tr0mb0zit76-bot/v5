<?php

namespace App\Console\Commands;

use App\Services\ImportCost\EecTnVedSyncService;
use App\Services\ImportCost\Pp1291ReferenceSyncService;
use Illuminate\Console\Command;

class SyncImportCostReferencesCommand extends Command
{
    protected $signature = 'import-cost:sync-references {--eec-only : Только синхронизация ЕЭК OData} {--pp1291-only : Только ПП № 1291}';

    protected $description = 'Обновить справочники калькулятора растаможки (ЕЭК OData + ПП РФ № 1291)';

    public function handle(
        EecTnVedSyncService $eecSync,
        Pp1291ReferenceSyncService $pp1291Sync,
    ): int {
        $eecOnly = (bool) $this->option('eec-only');
        $ppOnly = (bool) $this->option('pp1291-only');

        if ($eecOnly && $ppOnly) {
            $this->error('Нельзя указывать --eec-only и --pp1291-only одновременно.');

            return self::FAILURE;
        }

        $exit = self::SUCCESS;

        if (! $eecOnly) {
            $pp = $pp1291Sync->sync();
            $this->line('[ПП № 1291] '.$pp['message']);
            if ($pp['status'] === 'failed') {
                $exit = self::FAILURE;
            }
        }

        if (! $ppOnly) {
            $eec = $eecSync->sync();
            $this->line('[ЕЭК OData] '.$eec['message']);
            if ($eec['status'] === 'failed') {
                $exit = self::FAILURE;
            }
        }

        return $exit;
    }
}
