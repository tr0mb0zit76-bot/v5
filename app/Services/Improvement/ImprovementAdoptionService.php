<?php

namespace App\Services\Improvement;

use App\Models\ImprovementAdoption;
use App\Models\ImprovementExperiment;
use App\Models\ImprovementHypothesis;
use App\Models\SalesScript;
use App\Models\SalesScriptNode;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class ImprovementAdoptionService
{
    public const TARGET_SALES_SCRIPT_NODE = 'sales_script_node';

    public function adopt(ImprovementExperiment $experiment, User $actor): ImprovementAdoption
    {
        if ($experiment->verdict !== ImprovementExperiment::VERDICT_ADOPT_B) {
            throw ValidationException::withMessages([
                'experiment' => 'Закрепить можно только при вердикте adopt_b.',
            ]);
        }

        if ($experiment->adoption !== null) {
            return $experiment->adoption;
        }

        $hypothesis = $experiment->hypothesis;
        if ($hypothesis === null) {
            throw ValidationException::withMessages([
                'experiment' => 'У эксперимента нет гипотезы.',
            ]);
        }

        return DB::transaction(function () use ($experiment, $hypothesis, $actor): ImprovementAdoption {
            $scriptHint = $this->resolveScriptHint($hypothesis);
            $summary = 'Внедрить гипотезу: '.$hypothesis->text;
            if ($scriptHint['editor_url'] ?? null) {
                $summary .= "\nРедактор скрипта: ".$scriptHint['editor_url'];
            }

            $taskId = null;
            if (Schema::hasTable('tasks')) {
                $task = Task::query()->create([
                    'number' => $this->nextTaskNumber(),
                    'title' => 'Контур улучшений: внедрить гипотезу #'.$hypothesis->id,
                    'description' => $summary."\n\nЭксперимент: {$experiment->name}\nВердикт: ".$experiment->verdict_note
                        .($scriptHint['apply_hint'] ?? ''),
                    'status' => 'new',
                    'priority' => 'medium',
                    'due_at' => now()->addDays(7),
                    'created_by' => $actor->id,
                    'responsible_id' => $actor->id,
                    'meta' => array_filter([
                        'improvement_experiment_id' => $experiment->id,
                        'improvement_hypothesis_id' => $hypothesis->id,
                        'sales_script_version_id' => $scriptHint['version_id'] ?? null,
                        'script_editor_url' => $scriptHint['editor_url'] ?? null,
                        'proposed_body_variant_b' => $hypothesis->category === 'script' ? $hypothesis->text : null,
                    ]),
                ]);
                $taskId = $task->id;
            }

            $adoption = ImprovementAdoption::query()->create([
                'experiment_id' => $experiment->id,
                'hypothesis_id' => $hypothesis->id,
                'target_type' => $taskId !== null ? ImprovementAdoption::TARGET_TASK : ImprovementAdoption::TARGET_MANUAL_NOTE,
                'target_id' => $taskId,
                'summary' => $summary,
                'adopted_by' => $actor->id,
                'adopted_at' => now(),
                'meta' => [
                    'proposed_body_variant_b' => $hypothesis->category === 'script' ? $hypothesis->text : null,
                    'sales_script_version_id' => $scriptHint['version_id'] ?? null,
                    'script_editor_url' => $scriptHint['editor_url'] ?? null,
                    'script_applied' => false,
                ],
            ]);

            $hypothesis->update(['status' => ImprovementHypothesis::STATUS_ADOPTED]);

            return $adoption;
        });
    }

    /**
     * HITL: записать текст гипотезы в body_variant_b узла и включить A/B на узле.
     * Не перезаписывает основной body.
     */
    public function applyToScriptNode(ImprovementAdoption $adoption, SalesScriptNode $node, User $actor): ImprovementAdoption
    {
        $meta = $adoption->meta ?? [];
        $proposed = trim((string) ($meta['proposed_body_variant_b'] ?? ''));
        if ($proposed === '') {
            $proposed = trim((string) ($adoption->hypothesis?->text ?? ''));
        }
        if ($proposed === '') {
            throw ValidationException::withMessages([
                'adoption' => 'Нет текста для варианта B.',
            ]);
        }

        $versionId = $meta['sales_script_version_id'] ?? null;
        if ($versionId !== null && (int) $node->sales_script_version_id !== (int) $versionId) {
            throw ValidationException::withMessages([
                'node' => 'Узел не из рекомендованной версии скрипта.',
            ]);
        }

        $node->update([
            'body_variant_b' => $proposed,
            'ab_enabled' => true,
            'ab_variant_b_weight' => $node->ab_variant_b_weight ?: 50,
            'hint' => trim((string) ($node->hint ?? '')."\n[Контур улучшений #{$adoption->id}] ".$proposed),
        ]);

        $meta['script_applied'] = true;
        $meta['applied_node_id'] = $node->id;
        $meta['applied_by'] = $actor->id;
        $meta['applied_at'] = now()->toIso8601String();

        $adoption->update([
            'target_type' => self::TARGET_SALES_SCRIPT_NODE,
            'target_id' => $node->id,
            'meta' => $meta,
            'summary' => $adoption->summary."\nПрименено к узлу #{$node->id} (variant B).",
        ]);

        return $adoption->fresh(['hypothesis']);
    }

    /**
     * @return array{version_id: int|null, editor_url: string|null, apply_hint: string}
     */
    private function resolveScriptHint(ImprovementHypothesis $hypothesis): array
    {
        if ($hypothesis->category !== 'script' || ! Schema::hasTable('sales_scripts')) {
            return ['version_id' => null, 'editor_url' => null, 'apply_hint' => ''];
        }

        $script = SalesScript::query()->orderBy('id')->first();
        $version = $script?->activeVersions()->orderByDesc('published_at')->first()
            ?? $script?->versions()->orderByDesc('id')->first();

        if ($version === null) {
            return ['version_id' => null, 'editor_url' => null, 'apply_hint' => ''];
        }

        $url = route('scripts.editor.versions.graph', $version);

        return [
            'version_id' => $version->id,
            'editor_url' => $url,
            'apply_hint' => "\n\nL5: можно включить A/B на узле (body_variant_b) через «Улучшения → История».",
        ];
    }

    private function nextTaskNumber(): string
    {
        $prefix = 'TSK-'.now()->format('ymd');
        $sequence = DB::table('tasks')
            ->where('number', 'like', $prefix.'-%')
            ->count() + 1;

        return sprintf('%s-%03d', $prefix, $sequence);
    }
}
