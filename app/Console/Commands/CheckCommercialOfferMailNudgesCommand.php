<?php

namespace App\Console\Commands;

use App\Services\Commercial\CommercialNudgeProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CheckCommercialOfferMailNudgesCommand extends Command
{
    protected $signature = 'commercial:check-offer-mail-nudges';

    protected $description = 'Устаревший алиас: делегирует в commercial:process-nudges';

    public function handle(CommercialNudgeProcessor $processor): int
    {
        if (! Schema::hasTable('tasks') || ! Schema::hasTable('leads')) {
            return self::SUCCESS;
        }

        $result = $processor->process();

        $this->info("Создано задач-напоминаний: {$result['created']}");

        return self::SUCCESS;
    }
}
