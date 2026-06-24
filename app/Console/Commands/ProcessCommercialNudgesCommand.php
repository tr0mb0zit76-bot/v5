<?php

namespace App\Console\Commands;

use App\Services\Commercial\CommercialNudgeProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ProcessCommercialNudgesCommand extends Command
{
    protected $signature = 'commercial:process-nudges';

    protected $description = 'Создаёт задачи-напоминания по правилам этапов воронки (КП, SLA, контакт, лента)';

    public function handle(CommercialNudgeProcessor $processor): int
    {
        if (! Schema::hasTable('tasks') || ! Schema::hasTable('leads')) {
            return self::SUCCESS;
        }

        $result = $processor->process();

        $this->info(sprintf(
            'Nudges: создано %d, пропущено (дубль) %d',
            $result['created'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
