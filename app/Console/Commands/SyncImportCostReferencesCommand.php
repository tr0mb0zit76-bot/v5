<?php

namespace App\Console\Commands;

use App\Services\ImportCost\AltaReferenceSyncService;
use App\Services\ImportCost\EecTnVedSyncService;
use App\Services\ImportCost\KodTnVedReferenceSyncService;
use App\Services\ImportCost\Pp1291ReferenceSyncService;
use Illuminate\Console\Command;

class SyncImportCostReferencesCommand extends Command
{
    protected $signature = 'import-cost:sync-references
                            {--eec-only : Только синхронизация ЕЭК OData}
                            {--pp1291-only : Только ПП № 1291}
                            {--alta-only : Только дозаполнение ставок через Alta API}
                            {--kodtnved-only : Только дозаполнение ставок с kodtnved.ru}';

    protected $description = 'Обновить справочники калькулятора растаможки (Alta API + kodtnved.ru + ЕЭК OData + ПП РФ № 1291)';

    public function handle(
        AltaReferenceSyncService $altaSync,
        EecTnVedSyncService $eecSync,
        KodTnVedReferenceSyncService $kodtnvedSync,
        Pp1291ReferenceSyncService $pp1291Sync,
    ): int {
        $eecOnly = (bool) $this->option('eec-only');
        $ppOnly = (bool) $this->option('pp1291-only');
        $altaOnly = (bool) $this->option('alta-only');
        $kodtnvedOnly = (bool) $this->option('kodtnved-only');

        $exclusiveFlags = array_filter([$eecOnly, $ppOnly, $altaOnly, $kodtnvedOnly]);

        if (count($exclusiveFlags) > 1) {
            $this->error('Укажите не более одного флага: --eec-only, --pp1291-only, --alta-only или --kodtnved-only.');

            return self::FAILURE;
        }

        $exit = self::SUCCESS;

        if (! $eecOnly && ! $kodtnvedOnly && ! $altaOnly) {
            $pp = $pp1291Sync->sync();
            $this->line('[ПП № 1291] '.$pp['message']);
            if ($pp['status'] === 'failed') {
                $exit = self::FAILURE;
            }
        }

        if (! $ppOnly && ! $kodtnvedOnly && ! $altaOnly) {
            $eecSync->seedFromConfig();
            $eec = $eecSync->sync();
            $this->line('[ЕЭК OData] '.$eec['message']);
            if ($eec['status'] === 'failed') {
                $exit = self::FAILURE;
            }
        }

        if (! $eecOnly && ! $ppOnly && ! $kodtnvedOnly) {
            $alta = $altaSync->sync();
            $this->line('[Alta API] '.$alta['message']);
            if ($alta['status'] === 'failed') {
                $exit = self::FAILURE;
            }
        }

        if (! $eecOnly && ! $ppOnly && ! $altaOnly) {
            $kodtnved = $kodtnvedSync->sync();
            $this->line('[kodtnved.ru] '.$kodtnved['message']);
            if ($kodtnved['status'] === 'failed') {
                $exit = self::FAILURE;
            }
        }

        return $exit;
    }
}
