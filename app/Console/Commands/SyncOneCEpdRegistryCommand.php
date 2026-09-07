<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\OneC\OneCEpdRegistrySyncService;
use Illuminate\Console\Command;

class SyncOneCEpdRegistryCommand extends Command
{
    protected $signature = 'one-c:sync-epd-registry
        {--publication= : Код публикации (autalliance|gross|profsfera)}
        {--top= : Максимум строк реестра за публикацию}';

    protected $description = 'Синхронизировать РеестрЭПД из 1С в локальный грид ЭПД';

    public function handle(OneCEpdRegistrySyncService $sync): int
    {
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
        $this->line(json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return ($stats['errors'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
