<?php

namespace App\Observers;

use App\Models\Lead;
use App\Services\Improvement\ImprovementExperimentAssignmentService;
use App\Support\CrmFeatureCatalog;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class LeadImprovementExperimentObserver
{
    public function __construct(
        private readonly ImprovementExperimentAssignmentService $assignments,
    ) {}

    public function saved(Lead $lead): void
    {
        if (! CrmFeatureCatalog::isEnabled('improvement_loop')) {
            return;
        }

        if (! Schema::hasTable('improvement_experiment_assignments')) {
            return;
        }

        if (! $lead->wasChanged('status') && ! $lead->wasRecentlyCreated) {
            return;
        }

        if (! in_array($lead->status, ['won', 'lost'], true)) {
            return;
        }

        try {
            $this->assignments->syncLead($lead);
        } catch (Throwable) {
            // Контур улучшений не должен ломать сохранение лида.
        }
    }
}
