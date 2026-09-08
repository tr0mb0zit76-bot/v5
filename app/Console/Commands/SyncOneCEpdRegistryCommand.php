<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\OneC\OneCEpdEtrnStatusSyncService;
use App\Services\OneC\OneCEpdRegistrySyncService;
use Illuminate\Console\Command;

class SyncOneCEpdRegistryCommand extends Command
{
    protected $signature = 'one-c:sync-epd-registry
        {--publication= : Код публикации (autalliance|gross|profsfera)}
        {--top= : Максимум строк реестра за публикацию}
        {--skip-etrn-enrich : Не обогащать ЭТрН и не ставить ЭДО-отметки}';

    protected $description = 'Синхронизировать РеестрЭПД из 1С в локальный грид ЭПД (+ авто-ЭДО ЭТрН)';

    public function handle(
        OneCEpdRegistrySyncService $sync,
        OneCEpdEtrnStatusSyncService $etrnSync,
    ): int {
        if (! (bool) config('one_c.enabled')) {
            $this->error('ONE_C_ENABLED=false');

            return self::FAILURE;
        }

        $topOption = $this->option('top');
        $top = is_numeric($topOption) ? (int) $topOption : null;
        $publication = $this->option('publication');
        $publicationCode = is_string($publication) && trim($publication) !== ''
            ? trim($publication)
            : null;

        $stats = $sync->sync($publicationCode, $top);

        $etrnStats = null;
        if (! (bool) $this->option('skip-etrn-enrich')) {
            $etrnStats = $etrnSync->sync($publicationCode, $top);
        }

        $this->line(json_encode([
            'registry' => $stats,
            'etrn' => $etrnStats,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $errors = (int) ($stats['errors'] ?? 0) + (int) ($etrnStats['errors'] ?? 0);

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
