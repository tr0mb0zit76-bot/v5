<?php

namespace App\Console\Commands;

use App\Services\Improvement\ImprovementSignalCollector;
use App\Support\CrmFeatureCatalog;
use Illuminate\Console\Command;

class CollectImprovementSignalsCommand extends Command
{
    protected $signature = 'improvement:collect-signals
                            {--days=30 : Окно наблюдения в днях}
                            {--domains= : Через запятую: sales,documents,fleet,finance}';

    protected $description = 'Контур улучшений: собрать сигналы Observe/Diagnose (мультидомен)';

    public function handle(ImprovementSignalCollector $collector): int
    {
        if (! CrmFeatureCatalog::isEnabled('improvement_loop')) {
            $this->warn('Feature improvement_loop выключен.');

            return self::SUCCESS;
        }

        $days = max(7, min(180, (int) $this->option('days')));
        $domainsOption = trim((string) $this->option('domains'));
        $domains = $domainsOption !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $domainsOption))))
            : null;

        $created = $collector->collect($days, $domains);
        $this->info('Сигналов записано/обновлено: '.count($created));

        return self::SUCCESS;
    }
}
