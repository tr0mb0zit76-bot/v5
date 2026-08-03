<?php

namespace App\Services\Improvement;

use App\Models\ImprovementExperiment;
use App\Models\ImprovementExperimentAssignment;
use App\Models\Lead;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

final class ImprovementExperimentMetricsService
{
    public function __construct(
        private readonly ImprovementAbStatistics $stats,
    ) {}

    /**
     * @param  list<int>  $userIds
     * @return array{closed: int, won: int, lost: int, win_rate_pct: float}
     */
    public function winRateForUsers(array $userIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! Schema::hasTable('leads') || $userIds === []) {
            return ['closed' => 0, 'won' => 0, 'lost' => 0, 'win_rate_pct' => 0.0];
        }

        $leads = Lead::query()
            ->whereIn('status', ['won', 'lost'])
            ->whereIn('responsible_id', $userIds)
            ->whereBetween('updated_at', [$from->startOfDay(), $to->endOfDay()])
            ->get(['id', 'status']);

        return $this->rateFromRows($leads->all());
    }

    /**
     * @return array{closed: int, won: int, lost: int, win_rate_pct: float}
     */
    public function winRateForAssignments(ImprovementExperiment $experiment, string $variant): array
    {
        if (! Schema::hasTable('improvement_experiment_assignments')) {
            return ['closed' => 0, 'won' => 0, 'lost' => 0, 'win_rate_pct' => 0.0];
        }

        $rows = ImprovementExperimentAssignment::query()
            ->where('experiment_id', $experiment->id)
            ->where('variant', $variant)
            ->whereIn('outcome', ['won', 'lost'])
            ->get(['outcome']);

        $won = $rows->where('outcome', 'won')->count();
        $lost = $rows->where('outcome', 'lost')->count();
        $closed = $won + $lost;

        return [
            'closed' => $closed,
            'won' => $won,
            'lost' => $lost,
            'win_rate_pct' => $closed > 0 ? round($won / $closed * 100, 1) : 0.0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(ImprovementExperiment $experiment): array
    {
        $mode = $experiment->assignment_mode ?: ImprovementExperiment::ASSIGNMENT_MANAGERS;
        $cohort = $experiment->cohort ?? [];
        $from = CarbonImmutable::parse($experiment->starts_on?->toDateString() ?? now()->toDateString());
        $to = CarbonImmutable::parse($experiment->ends_on?->toDateString() ?? now()->toDateString());

        if ($mode === ImprovementExperiment::ASSIGNMENT_LEADS) {
            $variantA = $this->winRateForAssignments($experiment, ImprovementExperimentAssignment::VARIANT_A);
            $variantB = $this->winRateForAssignments($experiment, ImprovementExperimentAssignment::VARIANT_B);
        } else {
            $aIds = array_values(array_map('intval', $cohort['variant_a_user_ids'] ?? $cohort['user_ids_a'] ?? []));
            $bIds = array_values(array_map('intval', $cohort['variant_b_user_ids'] ?? $cohort['user_ids_b'] ?? []));
            $variantA = $this->winRateForUsers($aIds, $from, $to);
            $variantB = $this->winRateForUsers($bIds, $from, $to);
        }

        $stats = $this->stats->compareWinRates($variantA, $variantB);

        return [
            'metric_key' => $experiment->metric_key ?: 'win_rate',
            'assignment_mode' => $mode,
            'variant_a' => $variantA,
            'variant_b' => $variantB,
            'stats' => $stats,
            'measured_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Live preview for a running experiment (does not persist).
     *
     * @return array<string, mixed>
     */
    public function liveSnapshot(ImprovementExperiment $experiment): array
    {
        $clone = $experiment->replicate();
        $clone->id = $experiment->id;
        $clone->ends_on = $experiment->ends_on ?? now()->toDateString();
        $clone->exists = true;

        return $this->snapshot($clone);
    }

    /**
     * @param  list<object|array<string, mixed>>  $rows
     * @return array{closed: int, won: int, lost: int, win_rate_pct: float}
     */
    private function rateFromRows(array $rows): array
    {
        $won = 0;
        $lost = 0;
        foreach ($rows as $row) {
            $status = is_array($row) ? ($row['status'] ?? null) : ($row->status ?? null);
            if ($status === 'won') {
                $won++;
            } elseif ($status === 'lost') {
                $lost++;
            }
        }
        $closed = $won + $lost;

        return [
            'closed' => $closed,
            'won' => $won,
            'lost' => $lost,
            'win_rate_pct' => $closed > 0 ? round($won / $closed * 100, 1) : 0.0,
        ];
    }
}
