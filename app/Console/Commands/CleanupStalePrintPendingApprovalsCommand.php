<?php

namespace App\Console\Commands;

use App\Services\OrderPrintDocumentWorkflowService;
use Illuminate\Console\Command;

class CleanupStalePrintPendingApprovalsCommand extends Command
{
    protected $signature = 'print-workflow:cleanup-stale-pending';

    protected $description = 'Снять с очереди подписи устаревшие print-заявки, если уже есть более новая согласованная версия';

    public function handle(OrderPrintDocumentWorkflowService $workflow): int
    {
        $count = $workflow->cleanupStalePendingApprovals();
        $this->info("Superseded stale pending approvals: {$count}");

        return self::SUCCESS;
    }
}
