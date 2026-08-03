<?php

namespace App\Services\Improvement;

use App\Models\ImprovementExperiment;
use App\Models\ImprovementExperimentAssignment;
use App\Models\Lead;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

final class ImprovementExperimentAssignmentService
{
    /**
     * Назначить / обновить исход лида во всех running lead-mode экспериментах.
     */
    public function syncLead(Lead $lead): void
    {
        if (! Schema::hasTable('improvement_experiment_assignments')) {
            return;
        }

        if (! in_array($lead->status, ['won', 'lost'], true)) {
            return;
        }

        $responsibleId = (int) ($lead->responsible_id ?? 0);
        if ($responsibleId <= 0) {
            return;
        }

        $experiments = ImprovementExperiment::query()
            ->where('status', ImprovementExperiment::STATUS_RUNNING)
            ->where('assignment_mode', ImprovementExperiment::ASSIGNMENT_LEADS)
            ->get();

        foreach ($experiments as $experiment) {
            if (! $this->leadInWindow($experiment, $lead)) {
                continue;
            }

            $pool = $this->poolUserIds($experiment);
            if ($pool === [] || ! in_array($responsibleId, $pool, true)) {
                continue;
            }

            $this->assignOrUpdate($experiment, $lead);
        }
    }

    /**
     * Бэкфилл закрытых лидов за период эксперимента.
     *
     * @return int число затронутых assignments
     */
    public function backfill(ImprovementExperiment $experiment): int
    {
        if ($experiment->assignment_mode !== ImprovementExperiment::ASSIGNMENT_LEADS) {
            return 0;
        }

        if (! Schema::hasTable('leads') || ! Schema::hasTable('improvement_experiment_assignments')) {
            return 0;
        }

        $pool = $this->poolUserIds($experiment);
        if ($pool === []) {
            return 0;
        }

        $from = CarbonImmutable::parse($experiment->starts_on?->toDateString() ?? now()->toDateString())->startOfDay();
        $to = CarbonImmutable::parse($experiment->ends_on?->toDateString() ?? now()->toDateString())->endOfDay();

        $leads = Lead::query()
            ->whereIn('status', ['won', 'lost'])
            ->whereIn('responsible_id', $pool)
            ->whereBetween('updated_at', [$from, $to])
            ->get(['id', 'status', 'responsible_id', 'updated_at']);

        $count = 0;
        foreach ($leads as $lead) {
            $this->assignOrUpdate($experiment, $lead);
            $count++;
        }

        return $count;
    }

    public function variantForLead(ImprovementExperiment $experiment, int $leadId): string
    {
        $hash = crc32($experiment->id.'|'.$leadId);
        // unsigned on 32-bit; force positive
        $bucket = $hash % 2;

        return $bucket === 0
            ? ImprovementExperimentAssignment::VARIANT_A
            : ImprovementExperimentAssignment::VARIANT_B;
    }

    private function assignOrUpdate(ImprovementExperiment $experiment, Lead $lead): void
    {
        $variant = $this->variantForLead($experiment, (int) $lead->id);

        ImprovementExperimentAssignment::query()->updateOrCreate(
            [
                'experiment_id' => $experiment->id,
                'lead_id' => $lead->id,
            ],
            [
                'variant' => $variant,
                'outcome' => $lead->status,
                'assigned_at' => now(),
                'closed_at' => $lead->updated_at ?? now(),
            ],
        );
    }

    private function leadInWindow(ImprovementExperiment $experiment, Lead $lead): bool
    {
        $from = CarbonImmutable::parse($experiment->starts_on?->toDateString() ?? now()->toDateString())->startOfDay();
        $to = $experiment->ends_on !== null
            ? CarbonImmutable::parse($experiment->ends_on->toDateString())->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        $at = CarbonImmutable::parse($lead->updated_at ?? now());

        return $at->betweenIncluded($from, $to);
    }

    /**
     * @return list<int>
     */
    private function poolUserIds(ImprovementExperiment $experiment): array
    {
        $cohort = $experiment->cohort ?? [];
        $pool = $cohort['pool_user_ids'] ?? null;

        if (is_array($pool) && $pool !== []) {
            return array_values(array_unique(array_map('intval', $pool)));
        }

        $a = array_map('intval', $cohort['variant_a_user_ids'] ?? []);
        $b = array_map('intval', $cohort['variant_b_user_ids'] ?? []);

        return array_values(array_unique(array_merge($a, $b)));
    }
}
