<?php

namespace App\Services\Improvement;

use App\Models\ImprovementHypothesis;
use App\Models\ImprovementPipelineRun;
use App\Models\ImprovementSignal;
use App\Models\Lead;
use App\Support\LeadCloseOutcomeFlagCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ImprovementHypothesisPipeline
{
    public function __construct(
        private readonly ImprovementHypothesisLlmClient $llm,
    ) {}

    /**
     * @return array{status: string, run_id: int|null, created: int, message?: string}
     */
    public function run(int $days = 30, bool $dryRun = false): array
    {
        if (! Schema::hasTable('improvement_hypotheses')) {
            return ['status' => 'unavailable', 'run_id' => null, 'created' => 0, 'message' => 'Таблицы контура не созданы.'];
        }

        if (! $this->llm->isAvailable()) {
            return ['status' => 'unavailable', 'run_id' => null, 'created' => 0, 'message' => 'LLM недоступен.'];
        }

        $started = hrtime(true);
        $run = ImprovementPipelineRun::query()->create([
            'status' => ImprovementPipelineRun::STATUS_RUNNING,
            'signals_used' => 0,
            'hypotheses_created' => 0,
        ]);

        try {
            $snippets = $this->collectLostSnippets($days);
            $openSignals = ImprovementSignal::query()
                ->where('status', ImprovementSignal::STATUS_OPEN)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();

            foreach ($openSignals as $signal) {
                $snippets[] = 'Сигнал: '.$signal->title;
            }

            $snippets = array_values(array_unique(array_filter(array_map('trim', $snippets))));

            if ($snippets === []) {
                $run->update([
                    'status' => ImprovementPipelineRun::STATUS_NO_DATA,
                    'duration_ms' => $this->elapsedMs($started),
                    'meta' => ['dry_run' => $dryRun],
                ]);

                return ['status' => 'no_data', 'run_id' => $run->id, 'created' => 0];
            }

            $pains = $this->llm->gatherPains($snippets);
            if ($pains === []) {
                $run->update([
                    'status' => ImprovementPipelineRun::STATUS_NO_DATA,
                    'signals_used' => $openSignals->count(),
                    'duration_ms' => $this->elapsedMs($started),
                    'meta' => ['snippets' => count($snippets)],
                ]);

                return ['status' => 'no_data', 'run_id' => $run->id, 'created' => 0];
            }

            $ideas = $this->llm->generateIdeas($pains);
            $validated = $this->llm->validateIdeas($ideas);
            $scored = $this->llm->scoreIdeas($validated !== [] ? $validated : $ideas);

            $primarySignalId = $openSignals->first()?->id;
            $created = 0;
            $blockedFingerprints = $this->recentBlockedFingerprints();

            foreach ($scored as $item) {
                $fingerprint = $this->fingerprint($item['category'], $item['text']);
                if (isset($blockedFingerprints[$fingerprint])) {
                    continue;
                }

                if (ImprovementHypothesis::query()
                    ->where('fingerprint', $fingerprint)
                    ->whereIn('status', [
                        ImprovementHypothesis::STATUS_DRAFT,
                        ImprovementHypothesis::STATUS_ACCEPTED,
                        ImprovementHypothesis::STATUS_IN_EXPERIMENT,
                    ])
                    ->exists()) {
                    continue;
                }

                if (! $dryRun) {
                    ImprovementHypothesis::query()->create([
                        'signal_id' => $primarySignalId,
                        'pipeline_run_id' => $run->id,
                        'category' => $item['category'],
                        'text' => $item['text'],
                        'short_reason' => $item['short_reason'],
                        'impact' => $item['impact'],
                        'confidence' => $item['confidence'],
                        'ease' => $item['ease'],
                        'score' => $item['score'],
                        'status' => ImprovementHypothesis::STATUS_DRAFT,
                        'source' => 'llm_pipeline',
                        'fingerprint' => $fingerprint,
                    ]);

                    if ($primarySignalId !== null) {
                        ImprovementSignal::query()
                            ->whereKey($primarySignalId)
                            ->where('status', ImprovementSignal::STATUS_OPEN)
                            ->update(['status' => ImprovementSignal::STATUS_LINKED]);
                    }
                }

                $created++;
            }

            $run->update([
                'status' => ImprovementPipelineRun::STATUS_SUCCESS,
                'signals_used' => $openSignals->count(),
                'hypotheses_created' => $created,
                'duration_ms' => $this->elapsedMs($started),
                'meta' => [
                    'dry_run' => $dryRun,
                    'pains' => count($pains),
                    'ideas' => count($ideas),
                    'validated' => count($validated),
                    'scored' => count($scored),
                ],
            ]);

            return ['status' => 'success', 'run_id' => $run->id, 'created' => $created];
        } catch (Throwable $e) {
            $run->update([
                'status' => ImprovementPipelineRun::STATUS_FAILED,
                'duration_ms' => $this->elapsedMs($started),
                'error_summary' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            return [
                'status' => 'failed',
                'run_id' => $run->id,
                'created' => 0,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return list<string>
     */
    private function collectLostSnippets(int $days): array
    {
        if (! Schema::hasTable('leads')) {
            return [];
        }

        $since = CarbonImmutable::now()->subDays(max(7, min(90, $days)));

        return Lead::query()
            ->where('status', 'lost')
            ->where('updated_at', '>=', $since)
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get(['id', 'close_outcome_primary_flag', 'lost_reason', 'title'])
            ->map(function (Lead $lead): string {
                $flag = LeadCloseOutcomeFlagCatalog::label($lead->close_outcome_primary_flag) ?? 'без флага';
                $note = trim((string) ($lead->lost_reason ?? ''));
                $note = $this->redact($note);

                return "Лид #{$lead->id}: причина «{$flag}»".($note !== '' ? ". Заметка: {$note}" : '');
            })
            ->all();
    }

    private function redact(string $text): string
    {
        $text = preg_replace('/\+?\d[\d\-\s()]{8,}\d/', '[телефон]', $text) ?? $text;
        $text = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $text) ?? $text;

        return mb_substr($text, 0, 500);
    }

    /**
     * @return array<string, true>
     */
    private function recentBlockedFingerprints(): array
    {
        $since = CarbonImmutable::now()->subDays(90);
        $rows = ImprovementHypothesis::query()
            ->whereNotNull('fingerprint')
            ->where('created_at', '>=', $since)
            ->where(function ($q): void {
                $q->whereIn('status', [
                    ImprovementHypothesis::STATUS_REJECTED,
                    ImprovementHypothesis::STATUS_ARCHIVED,
                ])->orWhereHas('experiments', function ($eq): void {
                    $eq->where('verdict', 'keep_a');
                });
            })
            ->pluck('fingerprint');

        $map = [];
        foreach ($rows as $fp) {
            if (is_string($fp) && $fp !== '') {
                $map[$fp] = true;
            }
        }

        return $map;
    }

    public function fingerprint(string $category, string $text): string
    {
        $norm = mb_strtolower(preg_replace('/\s+/u', ' ', trim($text)) ?? '');

        return hash('sha256', $category.'|'.$norm);
    }

    private function elapsedMs(int $startedHrtime): int
    {
        return (int) round((hrtime(true) - $startedHrtime) / 1_000_000);
    }
}
