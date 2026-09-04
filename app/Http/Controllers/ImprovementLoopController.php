<?php

namespace App\Http\Controllers;

use App\Models\ImprovementAdoption;
use App\Models\ImprovementExperiment;
use App\Models\ImprovementHypothesis;
use App\Models\ImprovementSignal;
use App\Models\SalesScriptNode;
use App\Models\User;
use App\Services\Improvement\ImprovementAdoptionService;
use App\Services\Improvement\ImprovementExperimentMetricsService;
use App\Services\Improvement\ImprovementExperimentService;
use App\Services\Improvement\ImprovementHypothesisPipeline;
use App\Services\Improvement\ImprovementSignalCollector;
use App\Support\CrmFeatureCatalog;
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ImprovementLoopController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->authorizeAccess($request);

        $tab = (string) $request->query('tab', 'signals');
        if (! in_array($tab, ['signals', 'hypotheses', 'experiments', 'history'], true)) {
            $tab = 'signals';
        }

        $tablesReady = Schema::hasTable('improvement_signals');

        return Inertia::render('Improvement/Index', [
            'tab' => $tab,
            'feature_enabled' => CrmFeatureCatalog::isEnabled('improvement_loop', $user),
            'tables_ready' => $tablesReady,
            'signals' => $tablesReady ? $this->serializeSignals() : [],
            'hypotheses' => $tablesReady && Schema::hasTable('improvement_hypotheses')
                ? $this->serializeHypotheses()
                : [],
            'experiments' => $tablesReady && Schema::hasTable('improvement_experiments')
                ? $this->serializeExperiments()
                : [],
            'history' => $tablesReady && Schema::hasTable('improvement_adoptions')
                ? $this->serializeHistory()
                : [],
            'managers' => $this->managerOptions(),
            'script_nodes' => $this->scriptNodeOptions(),
        ]);
    }

    public function dismissSignal(Request $request, ImprovementSignal $signal): RedirectResponse
    {
        $this->authorizeAccess($request);

        $signal->update(['status' => ImprovementSignal::STATUS_DISMISSED]);

        return back()->with('success', 'Сигнал скрыт.');
    }

    public function acceptHypothesis(Request $request, ImprovementHypothesis $hypothesis): RedirectResponse
    {
        $user = $this->authorizeAccess($request);

        if ($hypothesis->status !== ImprovementHypothesis::STATUS_DRAFT) {
            return back()->withErrors(['hypothesis' => 'Принять можно только черновик.']);
        }

        $hypothesis->update([
            'status' => ImprovementHypothesis::STATUS_ACCEPTED,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Гипотеза принята.');
    }

    public function rejectHypothesis(Request $request, ImprovementHypothesis $hypothesis): RedirectResponse
    {
        $user = $this->authorizeAccess($request);

        if ($hypothesis->status !== ImprovementHypothesis::STATUS_DRAFT) {
            return back()->withErrors(['hypothesis' => 'Отклонить можно только черновик.']);
        }

        $hypothesis->update([
            'status' => ImprovementHypothesis::STATUS_REJECTED,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Гипотеза отклонена.');
    }

    public function storeExperiment(
        Request $request,
        ImprovementHypothesis $hypothesis,
        ImprovementExperimentService $experiments,
    ): RedirectResponse {
        $user = $this->authorizeAccess($request);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'variant_a_label' => ['nullable', 'string', 'max:500'],
            'variant_b_label' => ['nullable', 'string', 'max:500'],
            'assignment_mode' => ['nullable', 'string', Rule::in([
                ImprovementExperiment::ASSIGNMENT_MANAGERS,
                ImprovementExperiment::ASSIGNMENT_LEADS,
            ])],
            'pool_user_ids' => ['nullable', 'array'],
            'pool_user_ids.*' => ['integer', 'exists:users,id'],
            'variant_a_user_ids' => ['nullable', 'array'],
            'variant_a_user_ids.*' => ['integer', 'exists:users,id'],
            'variant_b_user_ids' => ['nullable', 'array'],
            'variant_b_user_ids.*' => ['integer', 'exists:users,id'],
            'metric_key' => ['nullable', 'string', Rule::in(['win_rate'])],
        ]);

        $mode = $validated['assignment_mode'] ?? ImprovementExperiment::ASSIGNMENT_LEADS;
        $pool = $validated['pool_user_ids'] ?? [];
        if ($pool === []) {
            $pool = array_values(array_unique(array_merge(
                $validated['variant_a_user_ids'] ?? [],
                $validated['variant_b_user_ids'] ?? [],
            )));
        }

        $experiments->create($hypothesis, $user, [
            'name' => $validated['name'] ?? null,
            'starts_on' => $validated['starts_on'] ?? null,
            'ends_on' => $validated['ends_on'] ?? null,
            'metric_key' => $validated['metric_key'] ?? 'win_rate',
            'assignment_mode' => $mode,
            'variant_a' => ['label' => $validated['variant_a_label'] ?? 'Контроль (как сейчас)'],
            'variant_b' => ['label' => $validated['variant_b_label'] ?? $hypothesis->text],
            'cohort' => [
                'pool_user_ids' => $pool,
                'variant_a_user_ids' => $validated['variant_a_user_ids'] ?? [],
                'variant_b_user_ids' => $validated['variant_b_user_ids'] ?? [],
            ],
        ]);

        return redirect()
            ->route('improvement.index', ['tab' => 'experiments'])
            ->with('success', 'Эксперимент создан.');
    }

    public function startExperiment(
        Request $request,
        ImprovementExperiment $experiment,
        ImprovementExperimentService $experiments,
    ): RedirectResponse {
        $this->authorizeAccess($request);
        $experiments->start($experiment);

        return back()->with('success', 'Эксперимент запущен.');
    }

    public function completeExperiment(
        Request $request,
        ImprovementExperiment $experiment,
        ImprovementExperimentService $experiments,
    ): RedirectResponse {
        $user = $this->authorizeAccess($request);

        $validated = $request->validate([
            'verdict' => ['required', Rule::in([
                ImprovementExperiment::VERDICT_ADOPT_B,
                ImprovementExperiment::VERDICT_KEEP_A,
                ImprovementExperiment::VERDICT_INCONCLUSIVE,
            ])],
            'verdict_note' => ['required', 'string', 'max:2000'],
        ]);

        $experiments->complete($experiment, $user, $validated);

        return redirect()
            ->route('improvement.index', ['tab' => 'history'])
            ->with('success', 'Эксперимент завершён.');
    }

    public function cancelExperiment(
        Request $request,
        ImprovementExperiment $experiment,
        ImprovementExperimentService $experiments,
    ): RedirectResponse {
        $this->authorizeAccess($request);
        $experiments->cancel($experiment);

        return back()->with('success', 'Эксперимент отменён.');
    }

    public function applyAdoptionToScriptNode(
        Request $request,
        ImprovementAdoption $adoption,
        ImprovementAdoptionService $adoptions,
    ): RedirectResponse {
        $user = $this->authorizeAccess($request);

        $validated = $request->validate([
            'sales_script_node_id' => ['required', 'integer', 'exists:sales_script_nodes,id'],
        ]);

        $node = SalesScriptNode::query()->findOrFail((int) $validated['sales_script_node_id']);
        $adoptions->applyToScriptNode($adoption, $node, $user);

        return redirect()
            ->route('improvement.index', ['tab' => 'history'])
            ->with('success', 'Вариант B записан в узел скрипта (A/B включён).');
    }

    public function collectSignals(
        Request $request,
        ImprovementSignalCollector $collector,
    ): RedirectResponse {
        $this->authorizeAccess($request);
        $created = $collector->collect(30);

        return back()->with('success', 'Собрано сигналов: '.count($created));
    }

    public function runPipeline(
        Request $request,
        ImprovementHypothesisPipeline $pipeline,
    ): RedirectResponse {
        $this->authorizeAccess($request);
        $result = $pipeline->run(30);

        $message = match ($result['status']) {
            'success' => 'Пайплайн ок, гипотез: '.$result['created'],
            'no_data' => 'Недостаточно данных для гипотез.',
            'unavailable' => $result['message'] ?? 'Пайплайн недоступен.',
            default => $result['message'] ?? 'Пайплайн завершился с ошибкой.',
        };

        return redirect()
            ->route('improvement.index', ['tab' => 'hypotheses'])
            ->with($result['status'] === 'success' ? 'success' : 'error', $message);
    }

    private function authorizeAccess(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(CrmFeatureCatalog::isEnabled('improvement_loop', $user), 404);
        abort_unless(RoleAccess::canAccessImprovementLoop($user), 403);

        return $user;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeSignals(): array
    {
        return ImprovementSignal::query()
            ->whereIn('status', [ImprovementSignal::STATUS_OPEN, ImprovementSignal::STATUS_LINKED])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (ImprovementSignal $s): array => [
                'id' => $s->id,
                'domain' => $s->domain,
                'kind' => $s->kind,
                'severity' => $s->severity,
                'title' => $s->title,
                'payload' => $s->payload,
                'status' => $s->status,
                'period_from' => optional($s->period_from)?->toDateString(),
                'period_to' => optional($s->period_to)?->toDateString(),
                'created_at' => optional($s->created_at)?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeHypotheses(): array
    {
        return ImprovementHypothesis::query()
            ->orderByDesc('score')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (ImprovementHypothesis $h): array => [
                'id' => $h->id,
                'category' => $h->category,
                'text' => $h->text,
                'short_reason' => $h->short_reason,
                'impact' => $h->impact,
                'confidence' => $h->confidence,
                'ease' => $h->ease,
                'score' => $h->score,
                'status' => $h->status,
                'signal_id' => $h->signal_id,
                'created_at' => optional($h->created_at)?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeExperiments(): array
    {
        /** @var ImprovementExperimentMetricsService $metrics */
        $metrics = app(ImprovementExperimentMetricsService::class);

        return ImprovementExperiment::query()
            ->with('hypothesis:id,text,category,status')
            ->withCount('assignments')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(function (ImprovementExperiment $e) use ($metrics): array {
                $live = null;
                if ($e->status === ImprovementExperiment::STATUS_RUNNING) {
                    $live = $metrics->liveSnapshot($e);
                }

                return [
                    'id' => $e->id,
                    'name' => $e->name,
                    'status' => $e->status,
                    'metric_key' => $e->metric_key,
                    'assignment_mode' => $e->assignment_mode,
                    'variant_a' => $e->variant_a,
                    'variant_b' => $e->variant_b,
                    'cohort' => $e->cohort,
                    'starts_on' => optional($e->starts_on)?->toDateString(),
                    'ends_on' => optional($e->ends_on)?->toDateString(),
                    'result_snapshot' => $e->result_snapshot,
                    'live_snapshot' => $live,
                    'assignments_count' => (int) ($e->assignments_count ?? 0),
                    'verdict' => $e->verdict,
                    'verdict_note' => $e->verdict_note,
                    'hypothesis' => $e->hypothesis ? [
                        'id' => $e->hypothesis->id,
                        'text' => $e->hypothesis->text,
                        'category' => $e->hypothesis->category,
                        'status' => $e->hypothesis->status,
                    ] : null,
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeHistory(): array
    {
        $completed = ImprovementExperiment::query()
            ->with(['hypothesis:id,text,category', 'adoption'])
            ->where('status', ImprovementExperiment::STATUS_COMPLETED)
            ->orderByDesc('decided_at')
            ->limit(40)
            ->get()
            ->map(fn (ImprovementExperiment $e): array => [
                'type' => 'experiment',
                'id' => $e->id,
                'name' => $e->name,
                'verdict' => $e->verdict,
                'verdict_note' => $e->verdict_note,
                'result_snapshot' => $e->result_snapshot,
                'decided_at' => optional($e->decided_at)?->toIso8601String(),
                'hypothesis_text' => $e->hypothesis?->text,
                'adoption' => $e->adoption ? [
                    'id' => $e->adoption->id,
                    'target_type' => $e->adoption->target_type,
                    'target_id' => $e->adoption->target_id,
                    'summary' => $e->adoption->summary,
                    'meta' => $e->adoption->meta,
                    'adopted_at' => optional($e->adoption->adopted_at)?->toIso8601String(),
                ] : null,
            ])
            ->all();

        return $completed;
    }

    /**
     * @return list<array{id: int, label: string, version_id: int}>
     */
    private function scriptNodeOptions(): array
    {
        if (! Schema::hasTable('sales_script_nodes')) {
            return [];
        }

        return SalesScriptNode::query()
            ->orderBy('sales_script_version_id')
            ->orderBy('sort_order')
            ->limit(200)
            ->get(['id', 'sales_script_version_id', 'kind', 'body', 'client_key'])
            ->map(function (SalesScriptNode $node): array {
                $kind = $node->kind instanceof \BackedEnum ? $node->kind->value : (string) $node->kind;
                $snippet = mb_substr(trim(strip_tags((string) $node->body)), 0, 60);

                return [
                    'id' => $node->id,
                    'version_id' => (int) $node->sales_script_version_id,
                    'label' => '#'.$node->id.' · '.$kind.' · '.($snippet !== '' ? $snippet : ($node->client_key ?? 'узел')),
                ];
            })
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function managerOptions(): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        return User::query()
            ->visibleInLists()
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name'])
            ->map(fn (User $u): array => ['id' => $u->id, 'name' => $u->name])
            ->all();
    }
}
