<?php

namespace App\Services\Improvement;

use App\Models\ImprovementExperiment;
use App\Models\ImprovementHypothesis;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ImprovementExperimentService
{
    public function __construct(
        private readonly ImprovementExperimentMetricsService $metrics,
        private readonly ImprovementAdoptionService $adoptions,
        private readonly ImprovementExperimentAssignmentService $assignments,
        private readonly ImprovementNextCycleService $nextCycle,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(ImprovementHypothesis $hypothesis, User $actor, array $data): ImprovementExperiment
    {
        if ($hypothesis->status !== ImprovementHypothesis::STATUS_ACCEPTED) {
            throw ValidationException::withMessages([
                'hypothesis' => 'Эксперимент можно создать только из принятой гипотезы.',
            ]);
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $name = 'Эксперимент: '.mb_substr($hypothesis->text, 0, 80);
        }

        $variantA = is_array($data['variant_a'] ?? null) ? $data['variant_a'] : ['label' => 'Контроль (как сейчас)'];
        $variantB = is_array($data['variant_b'] ?? null) ? $data['variant_b'] : ['label' => $hypothesis->text];

        $mode = (string) ($data['assignment_mode'] ?? ImprovementExperiment::ASSIGNMENT_LEADS);
        if (! in_array($mode, [
            ImprovementExperiment::ASSIGNMENT_MANAGERS,
            ImprovementExperiment::ASSIGNMENT_LEADS,
        ], true)) {
            $mode = ImprovementExperiment::ASSIGNMENT_LEADS;
        }

        $cohort = is_array($data['cohort'] ?? null) ? $data['cohort'] : [];
        if ($mode === ImprovementExperiment::ASSIGNMENT_LEADS) {
            $pool = array_values(array_unique(array_map(
                'intval',
                $cohort['pool_user_ids']
                    ?? array_merge(
                        $cohort['variant_a_user_ids'] ?? [],
                        $cohort['variant_b_user_ids'] ?? [],
                    ),
            )));
            if ($pool === []) {
                throw ValidationException::withMessages([
                    'cohort' => 'Для режима «рандомизация лидов» укажите пул менеджеров.',
                ]);
            }
            $cohort['pool_user_ids'] = $pool;
        }

        return ImprovementExperiment::query()->create([
            'hypothesis_id' => $hypothesis->id,
            'name' => $name,
            'status' => ImprovementExperiment::STATUS_PLANNED,
            'variant_a' => $variantA,
            'variant_b' => $variantB,
            'metric_key' => (string) ($data['metric_key'] ?? 'win_rate'),
            'assignment_mode' => $mode,
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'cohort' => $cohort,
            'created_by' => $actor->id,
        ]);
    }

    public function start(ImprovementExperiment $experiment): ImprovementExperiment
    {
        if ($experiment->status !== ImprovementExperiment::STATUS_PLANNED) {
            throw ValidationException::withMessages([
                'experiment' => 'Запустить можно только запланированный эксперимент.',
            ]);
        }

        $hypothesis = $experiment->hypothesis;
        if ($hypothesis === null || $hypothesis->status !== ImprovementHypothesis::STATUS_ACCEPTED) {
            throw ValidationException::withMessages([
                'experiment' => 'Гипотеза должна быть в статусе accepted.',
            ]);
        }

        $experiment->update([
            'status' => ImprovementExperiment::STATUS_RUNNING,
            'starts_on' => $experiment->starts_on ?? now()->toDateString(),
        ]);

        $hypothesis->update(['status' => ImprovementHypothesis::STATUS_IN_EXPERIMENT]);

        $fresh = $experiment->fresh(['hypothesis']);
        if ($fresh !== null && $fresh->assignment_mode === ImprovementExperiment::ASSIGNMENT_LEADS) {
            $this->assignments->backfill($fresh);
        }

        return $fresh ?? $experiment;
    }

    /**
     * @param  array{verdict: string, verdict_note?: string|null}  $data
     */
    public function complete(ImprovementExperiment $experiment, User $actor, array $data): ImprovementExperiment
    {
        if ($experiment->status !== ImprovementExperiment::STATUS_RUNNING) {
            throw ValidationException::withMessages([
                'experiment' => 'Завершить можно только текущий эксперимент.',
            ]);
        }

        $verdict = (string) ($data['verdict'] ?? '');
        if (! in_array($verdict, [
            ImprovementExperiment::VERDICT_ADOPT_B,
            ImprovementExperiment::VERDICT_KEEP_A,
            ImprovementExperiment::VERDICT_INCONCLUSIVE,
        ], true)) {
            throw ValidationException::withMessages([
                'verdict' => 'Укажите вердикт: adopt_b, keep_a или inconclusive.',
            ]);
        }

        $note = trim((string) ($data['verdict_note'] ?? ''));
        if ($note === '') {
            throw ValidationException::withMessages([
                'verdict_note' => 'Кратко опишите вывод по эксперименту.',
            ]);
        }

        return DB::transaction(function () use ($experiment, $actor, $verdict, $note): ImprovementExperiment {
            if ($experiment->ends_on === null) {
                $experiment->ends_on = now()->toDateString();
            }

            if ($experiment->assignment_mode === ImprovementExperiment::ASSIGNMENT_LEADS) {
                $this->assignments->backfill($experiment);
            }

            $snapshot = $this->metrics->snapshot($experiment);

            $experiment->update([
                'status' => ImprovementExperiment::STATUS_COMPLETED,
                'ends_on' => $experiment->ends_on,
                'result_snapshot' => $snapshot,
                'verdict' => $verdict,
                'verdict_note' => $note,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ]);

            $hypothesis = $experiment->hypothesis;
            if ($hypothesis !== null) {
                if ($verdict === ImprovementExperiment::VERDICT_ADOPT_B) {
                    $this->adoptions->adopt($experiment->fresh(['hypothesis']), $actor);
                } elseif ($verdict === ImprovementExperiment::VERDICT_KEEP_A) {
                    $hypothesis->update(['status' => ImprovementHypothesis::STATUS_ARCHIVED]);
                } else {
                    $hypothesis->update(['status' => ImprovementHypothesis::STATUS_ACCEPTED]);
                }
            }

            $completed = $experiment->fresh(['hypothesis', 'adoption']);
            if ($completed !== null) {
                $this->nextCycle->recordOutcomeSignal($completed);
            }

            return $completed ?? $experiment;
        });
    }

    public function cancel(ImprovementExperiment $experiment): ImprovementExperiment
    {
        if (! in_array($experiment->status, [
            ImprovementExperiment::STATUS_PLANNED,
            ImprovementExperiment::STATUS_RUNNING,
        ], true)) {
            throw ValidationException::withMessages([
                'experiment' => 'Отменить можно только planned/running.',
            ]);
        }

        $experiment->update(['status' => ImprovementExperiment::STATUS_CANCELLED]);

        $hypothesis = $experiment->hypothesis;
        if ($hypothesis !== null && $hypothesis->status === ImprovementHypothesis::STATUS_IN_EXPERIMENT) {
            $hypothesis->update(['status' => ImprovementHypothesis::STATUS_ACCEPTED]);
        }

        return $experiment->fresh(['hypothesis']);
    }
}
