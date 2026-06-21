<?php

namespace App\Services\SalesScripts;

use App\Enums\SalesPlayEventType;
use App\Enums\SalesPlaySessionOutcome;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SalesScriptAnalyticsService
{
    /**
     * @return array{
     *     available: bool,
     *     period_days: int,
     *     since: string,
     *     min_sample_size: int,
     *     top_reactions: list<array<string, mixed>>,
     *     drop_off_nodes: list<array<string, mixed>>,
     *     reaction_matrix: list<array<string, mixed>>
     * }
     */
    public function reportForVersion(int $versionId, int $days = 30): array
    {
        $days = max(1, min(365, $days));
        $since = CarbonImmutable::now()->startOfDay()->subDays($days);
        $matrix = $this->reactionMatrix($versionId, $since);
        $minSample = $this->minSampleSize();

        return [
            'available' => true,
            'period_days' => $days,
            'since' => $since->toIso8601String(),
            'min_sample_size' => $minSample,
            'top_reactions' => $this->topReactions($matrix),
            'drop_off_nodes' => $this->dropOffNodes($matrix, $minSample),
            'reaction_matrix' => $matrix,
        ];
    }

    /**
     * @param  list<int|null>  $reactionClassIds
     * @return array<int, array{message: string, sample_size: int, success_rate_pct: float}>
     */
    public function playChoiceHints(int $versionId, int $nodeId, array $reactionClassIds, int $days = 30): array
    {
        $reactionClassIds = array_values(array_filter(array_map(
            fn (mixed $id): ?int => is_numeric($id) ? (int) $id : null,
            $reactionClassIds,
        )));

        if ($reactionClassIds === []) {
            return [];
        }

        $since = CarbonImmutable::now()->startOfDay()->subDays(max(1, min(365, $days)));
        $matrix = collect($this->reactionMatrix($versionId, $since))
            ->filter(fn (array $row): bool => (int) $row['node_id'] === $nodeId
                && in_array((int) $row['reaction_class_id'], $reactionClassIds, true));

        $minSample = $this->minSampleSize();
        $qualified = $matrix
            ->filter(fn (array $row): bool => (int) $row['transition_count'] >= $minSample)
            ->values();

        if ($qualified->count() < 2) {
            return [];
        }

        $bestRate = (float) $qualified->max('success_rate_pct');
        if ($bestRate <= 0.0) {
            return [];
        }

        $hints = [];
        foreach ($qualified as $row) {
            if ((float) $row['success_rate_pct'] < $bestRate) {
                continue;
            }

            $reactionId = (int) $row['reaction_class_id'];
            $hints[$reactionId] = [
                'message' => sprintf(
                    'По статистике (N=%d) эта ветка чаще ведёт к успеху: %s%% progress/won.',
                    (int) $row['transition_count'],
                    number_format((float) $row['success_rate_pct'], 0, ',', ' '),
                ),
                'sample_size' => (int) $row['transition_count'],
                'success_rate_pct' => (float) $row['success_rate_pct'],
            ];
        }

        return $hints;
    }

    public function exportCsvForVersion(int $versionId, int $days = 30): string
    {
        $report = $this->reportForVersion($versionId, $days);
        $lines = [
            implode(';', [
                'node_key',
                'node_id',
                'reaction_key',
                'reaction_label',
                'transitions',
                'completed',
                'success',
                'lost',
                'success_rate_pct',
                'lost_rate_pct',
            ]),
        ];

        foreach ($report['reaction_matrix'] as $row) {
            $lines[] = implode(';', [
                $this->csvCell($row['node_key'] ?? ''),
                (string) ($row['node_id'] ?? ''),
                $this->csvCell($row['reaction_key'] ?? ''),
                $this->csvCell($row['reaction_label'] ?? ''),
                (string) ($row['transition_count'] ?? 0),
                (string) ($row['completed_count'] ?? 0),
                (string) ($row['success_count'] ?? 0),
                (string) ($row['lost_count'] ?? 0),
                number_format((float) ($row['success_rate_pct'] ?? 0), 1, '.', ''),
                number_format((float) ($row['lost_rate_pct'] ?? 0), 1, '.', ''),
            ]);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reactionMatrix(int $versionId, CarbonImmutable $since): array
    {
        $successOutcomes = $this->successOutcomes();
        $successPlaceholders = implode(',', array_fill(0, count($successOutcomes), '?'));

        $rows = DB::table('sales_script_play_events as e')
            ->join('sales_script_play_sessions as s', 's.id', '=', 'e.sales_script_play_session_id')
            ->leftJoin('sales_script_nodes as n', 'n.id', '=', 'e.sales_script_node_id')
            ->leftJoin('sales_script_reaction_classes as rc', 'rc.id', '=', 'e.sales_script_reaction_class_id')
            ->where('e.type', SalesPlayEventType::RecordedReaction->value)
            ->where('s.is_trainer', false)
            ->where('s.sales_script_version_id', $versionId)
            ->where('s.created_at', '>=', $since)
            ->whereNotNull('e.sales_script_reaction_class_id')
            ->groupBy(
                'e.sales_script_node_id',
                'e.sales_script_reaction_class_id',
                'n.client_key',
                'rc.key',
                'rc.label',
            )
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->selectRaw('e.sales_script_node_id as node_id')
            ->selectRaw('n.client_key as node_key')
            ->selectRaw('e.sales_script_reaction_class_id as reaction_class_id')
            ->selectRaw('rc.key as reaction_key')
            ->selectRaw('rc.label as reaction_label')
            ->selectRaw('COUNT(*) as transition_count')
            ->selectRaw('SUM(CASE WHEN s.completed_at IS NOT NULL THEN 1 ELSE 0 END) as completed_count')
            ->selectRaw(
                "SUM(CASE WHEN s.outcome IN ({$successPlaceholders}) THEN 1 ELSE 0 END) as success_count",
                $successOutcomes,
            )
            ->selectRaw(
                'SUM(CASE WHEN s.outcome = ? THEN 1 ELSE 0 END) as lost_count',
                [SalesPlaySessionOutcome::Lost->value],
            )
            ->get();

        return $rows->map(function (object $row): array {
            $transitionCount = (int) $row->transition_count;
            $completedCount = (int) $row->completed_count;
            $successCount = (int) $row->success_count;
            $lostCount = (int) $row->lost_count;

            return [
                'node_id' => (int) $row->node_id,
                'node_key' => $row->node_key,
                'reaction_class_id' => (int) $row->reaction_class_id,
                'reaction_key' => $row->reaction_key,
                'reaction_label' => (string) ($row->reaction_label ?? '—'),
                'transition_count' => $transitionCount,
                'completed_count' => $completedCount,
                'success_count' => $successCount,
                'lost_count' => $lostCount,
                'success_rate_pct' => $completedCount > 0 ? round($successCount / $completedCount * 100, 1) : 0.0,
                'lost_rate_pct' => $completedCount > 0 ? round($lostCount / $completedCount * 100, 1) : 0.0,
            ];
        })->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $matrix
     * @return list<array<string, mixed>>
     */
    private function topReactions(array $matrix): array
    {
        return collect($matrix)
            ->groupBy('reaction_class_id')
            ->map(function (Collection $rows): array {
                $first = $rows->first();
                $transitionCount = (int) $rows->sum('transition_count');
                $successCount = (int) $rows->sum('success_count');
                $completedCount = (int) $rows->sum('completed_count');
                $lostCount = (int) $rows->sum('lost_count');

                return [
                    'reaction_class_id' => (int) ($first['reaction_class_id'] ?? 0),
                    'reaction_key' => $first['reaction_key'] ?? null,
                    'reaction_label' => (string) ($first['reaction_label'] ?? '—'),
                    'transition_count' => $transitionCount,
                    'success_rate_pct' => $completedCount > 0 ? round($successCount / $completedCount * 100, 1) : 0.0,
                    'lost_rate_pct' => $completedCount > 0 ? round($lostCount / $completedCount * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('transition_count')
            ->values()
            ->take(10)
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $matrix
     * @return list<array<string, mixed>>
     */
    private function dropOffNodes(array $matrix, int $minSample): array
    {
        return collect($matrix)
            ->filter(fn (array $row): bool => (int) $row['transition_count'] >= $minSample)
            ->groupBy('node_id')
            ->map(function (Collection $rows): array {
                $first = $rows->first();
                $transitionCount = (int) $rows->sum('transition_count');
                $successCount = (int) $rows->sum('success_count');
                $completedCount = (int) $rows->sum('completed_count');
                $lostCount = (int) $rows->sum('lost_count');
                $successRate = $completedCount > 0 ? round($successCount / $completedCount * 100, 1) : 0.0;

                return [
                    'node_id' => (int) ($first['node_id'] ?? 0),
                    'node_key' => $first['node_key'] ?? null,
                    'transition_count' => $transitionCount,
                    'success_rate_pct' => $successRate,
                    'lost_rate_pct' => $completedCount > 0 ? round($lostCount / $completedCount * 100, 1) : 0.0,
                    'worst_reaction_label' => $rows->sortByDesc('lost_rate_pct')->first()['reaction_label'] ?? null,
                ];
            })
            ->sortBy('success_rate_pct')
            ->values()
            ->take(10)
            ->all();
    }

    /**
     * @return list<string>
     */
    private function successOutcomes(): array
    {
        /** @var list<string> $configured */
        $configured = config('sales_scripts.analytics.success_outcomes', ['progress', 'quote_sent', 'won']);

        return $configured;
    }

    private function minSampleSize(): int
    {
        return max(1, (int) config('sales_scripts.analytics.min_sample_size', 10));
    }

    private function csvCell(string $value): string
    {
        $escaped = str_replace('"', '""', $value);

        return '"'.$escaped.'"';
    }
}
