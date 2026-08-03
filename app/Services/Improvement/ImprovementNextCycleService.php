<?php

namespace App\Services\Improvement;

use App\Models\ImprovementExperiment;
use App\Models\ImprovementSignal;

final class ImprovementNextCycleService
{
    /**
     * После вердикта — сигнал для следующего Observe/Propose цикла.
     */
    public function recordOutcomeSignal(ImprovementExperiment $experiment): ?ImprovementSignal
    {
        $verdict = (string) ($experiment->verdict ?? '');
        if ($verdict === '') {
            return null;
        }

        $stats = $experiment->result_snapshot['stats'] ?? [];
        $diff = $stats['diff_pp'] ?? null;
        $significant = (bool) ($stats['significant'] ?? false);

        $severity = match ($verdict) {
            ImprovementExperiment::VERDICT_ADOPT_B => 'info',
            ImprovementExperiment::VERDICT_KEEP_A => 'warn',
            default => 'info',
        };

        $title = match ($verdict) {
            ImprovementExperiment::VERDICT_ADOPT_B => 'Эксперимент #'.$experiment->id.': внедряем B — запланируйте закрепление в скрипте',
            ImprovementExperiment::VERDICT_KEEP_A => 'Эксперимент #'.$experiment->id.': B не сработал — не повторять ту же гипотезу',
            default => 'Эксперимент #'.$experiment->id.': исход неясен — соберите больше данных или другую гипотезу',
        };

        return ImprovementSignal::query()->create([
            'domain' => 'sales',
            'kind' => 'experiment_outcome',
            'severity' => $severity,
            'title' => $title,
            'payload' => [
                'experiment_id' => $experiment->id,
                'hypothesis_id' => $experiment->hypothesis_id,
                'verdict' => $verdict,
                'verdict_note' => $experiment->verdict_note,
                'diff_pp' => $diff,
                'significant' => $significant,
                'stats' => $stats,
                'result_snapshot' => $experiment->result_snapshot,
            ],
            'period_from' => $experiment->starts_on,
            'period_to' => $experiment->ends_on ?? now()->toDateString(),
            'source' => 'experiment',
            'status' => ImprovementSignal::STATUS_OPEN,
        ]);
    }
}
