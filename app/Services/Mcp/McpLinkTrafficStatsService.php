<?php

namespace App\Services\Mcp;

use App\Support\AiInteractionEventType;
use App\Support\McpToolDomainRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class McpLinkTrafficStatsService
{
    /**
     * @return array{
     *     days: int,
     *     total_calls: int,
     *     max_edge_calls: int,
     *     edges: array<string, array{
     *         source_key: string,
     *         target_key: string,
     *         calls: int,
     *         errors: int,
     *         top_tools: list<array{tool: string, calls: int}>
     *     }>,
     *     nodes: array<string, array{calls: int, errors: int}>
     * }
     */
    public function forPeriod(int $days = 7): array
    {
        $days = max(1, min($days, 90));

        if (! Schema::hasTable('ai_interaction_events')) {
            return $this->emptyPayload($days);
        }

        $since = now()->subDays($days);

        $rows = DB::table('ai_interaction_events')
            ->select('tool_name', 'ok', DB::raw('COUNT(*) as calls'))
            ->where('event_type', AiInteractionEventType::ToolInvoked->value)
            ->whereNotNull('tool_name')
            ->where('created_at', '>=', $since)
            ->groupBy('tool_name', 'ok')
            ->get();

        /** @var array<string, array{calls: int, errors: int, tools: array<string, int>}> $edgeBuckets */
        $edgeBuckets = [];
        /** @var array<string, array{calls: int, errors: int}> $nodeBuckets */
        $nodeBuckets = [];
        $totalCalls = 0;

        foreach ($rows as $row) {
            $toolName = (string) $row->tool_name;
            $count = (int) $row->calls;
            $isError = ! (bool) $row->ok;

            if ($count <= 0) {
                continue;
            }

            $totalCalls += $count;
            $primary = McpToolDomainRegistry::primaryDomainForTool($toolName);
            $pairs = McpToolDomainRegistry::crossDomainPairsForTool($toolName);

            $domainsTouched = array_values(array_unique(array_filter([
                $primary,
                ...array_merge(...array_map(
                    fn (array $pair): array => [$pair['source_key'], $pair['target_key']],
                    $pairs,
                )),
            ])));

            foreach ($domainsTouched as $domain) {
                $this->incrementNode($nodeBuckets, $domain, $count, $isError ? $count : 0);
            }

            if ($pairs === []) {
                continue;
            }

            foreach ($pairs as $pair) {
                $pairKey = McpToolDomainRegistry::normalizedPairKey(
                    $pair['source_key'],
                    $pair['target_key'],
                );

                if (! isset($edgeBuckets[$pairKey])) {
                    $edgeBuckets[$pairKey] = [
                        'calls' => 0,
                        'errors' => 0,
                        'tools' => [],
                    ];
                }

                $edgeBuckets[$pairKey]['calls'] += $count;

                if ($isError) {
                    $edgeBuckets[$pairKey]['errors'] += $count;
                }

                $edgeBuckets[$pairKey]['tools'][$toolName] = ($edgeBuckets[$pairKey]['tools'][$toolName] ?? 0) + $count;
            }
        }

        $edges = collect($edgeBuckets)
            ->map(function (array $bucket, string $pairKey): array {
                [$sourceKey, $targetKey] = explode('|', $pairKey, 2);

                return [
                    'source_key' => $sourceKey,
                    'target_key' => $targetKey,
                    'calls' => $bucket['calls'],
                    'errors' => $bucket['errors'],
                    'top_tools' => $this->topTools($bucket['tools']),
                ];
            })
            ->sortByDesc('calls')
            ->values()
            ->keyBy(fn (array $edge): string => McpToolDomainRegistry::normalizedPairKey(
                $edge['source_key'],
                $edge['target_key'],
            ))
            ->all();

        $maxEdgeCalls = collect($edges)->max('calls') ?? 0;

        return [
            'days' => $days,
            'total_calls' => $totalCalls,
            'max_edge_calls' => (int) $maxEdgeCalls,
            'edges' => $edges,
            'nodes' => $nodeBuckets,
        ];
    }

    /**
     * @param  array<string, array{calls: int, errors: int}>  $nodeBuckets
     */
    private function incrementNode(array &$nodeBuckets, string $key, int $calls, int $errors): void
    {
        if (! isset($nodeBuckets[$key])) {
            $nodeBuckets[$key] = ['calls' => 0, 'errors' => 0];
        }

        $nodeBuckets[$key]['calls'] += $calls;
        $nodeBuckets[$key]['errors'] += $errors;
    }

    /**
     * @param  array<string, int>  $tools
     * @return list<array{tool: string, calls: int}>
     */
    private function topTools(array $tools): array
    {
        return Collection::make($tools)
            ->sortDesc()
            ->take(3)
            ->map(fn (int $calls, string $tool): array => [
                'tool' => $tool,
                'calls' => $calls,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     days: int,
     *     total_calls: int,
     *     max_edge_calls: int,
     *     edges: array<string, mixed>,
     *     nodes: array<string, mixed>
     * }
     */
    private function emptyPayload(int $days): array
    {
        return [
            'days' => $days,
            'total_calls' => 0,
            'max_edge_calls' => 0,
            'edges' => [],
            'nodes' => [],
        ];
    }
}
