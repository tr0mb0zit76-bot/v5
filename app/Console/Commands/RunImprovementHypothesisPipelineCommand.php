<?php

namespace App\Console\Commands;

use App\Services\Improvement\ImprovementHypothesisPipeline;
use App\Support\CrmFeatureCatalog;
use Illuminate\Console\Command;

class RunImprovementHypothesisPipelineCommand extends Command
{
    protected $signature = 'improvement:run-hypothesis-pipeline
                            {--days=30 : Окно lost-лидов}
                            {--dry-run : Не писать гипотезы в БД}';

    protected $description = 'Контур улучшений: Propose pipeline (Археолог→Метрик)';

    public function handle(ImprovementHypothesisPipeline $pipeline): int
    {
        if (! CrmFeatureCatalog::isEnabled('improvement_loop')) {
            $this->warn('Feature improvement_loop выключен.');

            return self::SUCCESS;
        }

        $result = $pipeline->run(
            max(7, min(90, (int) $this->option('days'))),
            (bool) $this->option('dry-run'),
        );

        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return in_array($result['status'], ['success', 'no_data', 'unavailable'], true)
            ? self::SUCCESS
            : self::FAILURE;
    }
}
