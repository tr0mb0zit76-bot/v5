<?php

namespace App\Services\Mcp;

use App\Models\SalesScript;
use App\Models\SalesScriptNodeTemplate;
use App\Models\SalesScriptVersion;
use App\Models\User;
use App\Support\RoleAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class SalesScriptsMcpService
{
    /**
     * @return array{items: list<array<string, mixed>>, node_templates_count: int}
     */
    public function list(User $user, ?string $query = null): array
    {
        $this->requireAccess($user);

        $items = SalesScript::query()
            ->with(['versions' => fn ($builder) => $builder
                ->withCount(['nodes', 'transitions', 'playSessions'])
                ->orderByDesc('version_number')])
            ->when(filled($query), function (Builder $builder) use ($query): void {
                $builder->where(function (Builder $nested) use ($query): void {
                    $nested
                        ->where('title', 'like', '%'.trim((string) $query).'%')
                        ->orWhere('description', 'like', '%'.trim((string) $query).'%');
                });
            })
            ->orderBy('title')
            ->get()
            ->map(function (SalesScript $script): array {
                $active = $script->versions->first(
                    fn (SalesScriptVersion $version): bool => $version->isPublished(),
                );
                $latest = $script->versions->first();

                return [
                    'id' => (int) $script->id,
                    'title' => (string) $script->title,
                    'description' => $script->description,
                    'channel' => $script->channel,
                    'tags' => $script->tags ?? [],
                    'active_version' => $active ? $this->versionSummary($active) : null,
                    'latest_version' => $latest ? $this->versionSummary($latest) : null,
                ];
            })
            ->all();

        return [
            'items' => $items,
            'node_templates_count' => SalesScriptNodeTemplate::query()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function graph(User $user, int $scriptId, ?int $versionId = null): array
    {
        $this->requireAccess($user);

        $script = SalesScript::query()->find($scriptId);
        if ($script === null) {
            throw (new ModelNotFoundException)->setModel(SalesScript::class, [$scriptId]);
        }

        $version = $versionId !== null
            ? $script->versions()->whereKey($versionId)->first()
            : $script->versions()->orderByDesc('is_active')->orderByDesc('version_number')->first();

        if ($version === null) {
            throw (new ModelNotFoundException)->setModel(SalesScriptVersion::class, [$versionId]);
        }

        $version->load([
            'nodes' => fn ($builder) => $builder->orderBy('sort_order')->orderBy('id'),
            'transitions' => fn ($builder) => $builder
                ->with('reactionClass:id,key,label')
                ->orderBy('sort_order')
                ->orderBy('id'),
        ]);
        $version->loadCount(['nodes', 'transitions', 'playSessions']);

        return [
            'script' => [
                'id' => (int) $script->id,
                'title' => (string) $script->title,
                'description' => $script->description,
                'tags' => $script->tags ?? [],
            ],
            'version' => [
                ...$this->versionSummary($version),
                'entry_node_key' => $version->entry_node_key,
            ],
            'nodes' => $version->nodes->map(fn ($node): array => [
                'id' => (int) $node->id,
                'client_key' => (string) $node->client_key,
                'kind' => $node->kind->value,
                'body' => (string) $node->body,
                'hint' => $node->hint,
                'tags' => $node->tags ?? [],
                'capture_field_codes' => $node->capture_field_codes ?? [],
            ])->all(),
            'transitions' => $version->transitions->map(fn ($transition): array => [
                'id' => (int) $transition->id,
                'from_node_id' => (int) $transition->from_node_id,
                'to_node_id' => (int) $transition->to_node_id,
                'customer_label' => $transition->customer_label,
                'reaction' => $transition->reactionClass?->only(['id', 'key', 'label']),
                'conversation_effect' => $transition->conversation_effect,
                'momentum_delta' => $transition->momentum_delta,
                'next_move_preview' => $transition->next_move_preview,
                'target_type' => $transition->target_type ?? 'node',
                'target_sales_script_version_id' => $transition->target_sales_script_version_id,
            ])->all(),
        ];
    }

    /**
     * @return array{valid: bool, errors: list<array{code: string, message: string}>, warnings: list<array{code: string, message: string}>}
     */
    public function validate(User $user, int $scriptId, ?int $versionId = null): array
    {
        $graph = $this->graph($user, $scriptId, $versionId);
        $errors = [];
        $warnings = [];
        $nodes = collect($graph['nodes']);
        $transitions = collect($graph['transitions']);
        $nodeIds = $nodes->pluck('id')->map(fn (mixed $id): int => (int) $id);
        $entryKey = $graph['version']['entry_node_key'] ?? null;
        $entry = $nodes->firstWhere('client_key', $entryKey);

        if ($entry === null) {
            $errors[] = ['code' => 'missing_entry', 'message' => 'Стартовый узел не найден.'];
        }

        foreach ($transitions->groupBy('from_node_id') as $fromNodeId => $outgoing) {
            $duplicates = $outgoing
                ->filter(fn (array $transition): bool => isset($transition['reaction']['id']))
                ->groupBy(fn (array $transition): int => (int) $transition['reaction']['id'])
                ->filter(fn ($rows): bool => $rows->count() > 1);

            if ($duplicates->isNotEmpty()) {
                $errors[] = [
                    'code' => 'duplicate_reaction',
                    'message' => 'У узла #'.$fromNodeId.' несколько переходов с одинаковой реакцией.',
                ];
            }
        }

        $reachable = $entry === null ? collect() : $this->reachableNodeIds((int) $entry['id'], $transitions);
        foreach ($nodeIds->diff($reachable) as $nodeId) {
            $warnings[] = [
                'code' => 'unreachable_node',
                'message' => 'Узел #'.$nodeId.' недостижим из стартового узла.',
            ];
        }

        foreach ($transitions as $transition) {
            if (($transition['reaction']['id'] ?? null) !== null && blank($transition['customer_label'])) {
                $warnings[] = [
                    'code' => 'missing_customer_phrase',
                    'message' => 'У перехода #'.$transition['id'].' нет живой фразы клиента.',
                ];
            }
            if (($transition['conversation_effect'] ?? null) === null) {
                $warnings[] = [
                    'code' => 'automatic_effect',
                    'message' => 'У перехода #'.$transition['id'].' направление определяется автоматически.',
                ];
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private function requireAccess(User $user): void
    {
        if (! RoleAccess::canManageSalesScripts($user)
            && ! RoleAccess::canViewTrainerAnalytics($user)
            && ! RoleAccess::canViewAiAnalytics($user)) {
            throw new AuthenticationException('Нет доступа к скриптам продаж.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function versionSummary(SalesScriptVersion $version): array
    {
        return [
            'id' => (int) $version->id,
            'version_number' => (int) $version->version_number,
            'is_active' => (bool) $version->is_active,
            'published_at' => $version->published_at?->toIso8601String(),
            'nodes_count' => (int) ($version->nodes_count ?? 0),
            'transitions_count' => (int) ($version->transitions_count ?? 0),
            'play_sessions_count' => (int) ($version->play_sessions_count ?? 0),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $transitions
     * @return Collection<int, int>
     */
    private function reachableNodeIds(int $entryId, Collection $transitions): Collection
    {
        $reachable = collect([$entryId]);
        $queue = [$entryId];

        while ($queue !== []) {
            $from = array_shift($queue);
            foreach ($transitions->where('from_node_id', $from) as $transition) {
                $target = (int) $transition['to_node_id'];
                if (! $reachable->contains($target)) {
                    $reachable->push($target);
                    $queue[] = $target;
                }
            }
        }

        return $reachable;
    }
}
