<?php

namespace App\Console\Commands;

use App\Services\BusinessProcessPlaybookSeederService;
use Illuminate\Console\Command;

class SeedBusinessProcessPlaybooksCommand extends Command
{
    protected $signature = 'business-process:seed-playbooks
                            {--force : Перезаписать существующие playbook}';

    protected $description = 'Заполнить стандартные playbook и привязки скриптов для бизнес-процессов';

    public function handle(BusinessProcessPlaybookSeederService $seeder): int
    {
        $onlyEmpty = ! $this->option('force');

        $result = $seeder->seed($onlyEmpty);

        $this->info(sprintf(
            'Готово: процессов %d, этапов %d, скриптов привязано %d.',
            $result['processes'],
            $result['stages'],
            $result['scripts_linked'],
        ));

        return self::SUCCESS;
    }
}
