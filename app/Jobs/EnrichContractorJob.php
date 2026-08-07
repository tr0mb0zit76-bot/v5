<?php

namespace App\Jobs;

use App\Models\ContractorEnrichmentRun;
use App\Services\Contractor\ContractorEnrichmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Schema;
use Throwable;

class EnrichContractorJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $enrichmentRunId) {}

    public function handle(ContractorEnrichmentService $enrichment): void
    {
        if (! Schema::hasTable('contractor_enrichment_runs')) {
            return;
        }

        $run = ContractorEnrichmentRun::query()->find($this->enrichmentRunId);
        if ($run === null) {
            return;
        }

        if (in_array($run->status, [
            ContractorEnrichmentRun::STATUS_SUCCEEDED,
            ContractorEnrichmentRun::STATUS_FAILED,
        ], true)) {
            return;
        }

        $enrichment->run($run);
    }

    public function failed(?Throwable $exception): void
    {
        if (! Schema::hasTable('contractor_enrichment_runs')) {
            return;
        }

        $run = ContractorEnrichmentRun::query()->find($this->enrichmentRunId);
        if ($run === null) {
            return;
        }

        $run->update([
            'status' => ContractorEnrichmentRun::STATUS_FAILED,
            'error_message' => $exception !== null
                ? mb_substr($exception->getMessage(), 0, 1000)
                : 'Job failed',
            'finished_at' => now(),
        ]);
    }
}
