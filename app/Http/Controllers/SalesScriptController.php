<?php

namespace App\Http\Controllers;

use App\Enums\SalesPlayEventType;
use App\Enums\SalesPlaySessionOutcome;
use App\Http\Requests\AdvanceSalesScriptPlaySessionRequest;
use App\Http\Requests\CompleteSalesScriptPlaySessionRequest;
use App\Http\Requests\StoreSalesScriptPlaySessionRequest;
use App\Models\SalesScript;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptReactionClass;
use App\Models\SalesScriptVersion;
use App\Services\SalesScripts\SalesScriptPlaySessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class SalesScriptController extends Controller
{
    public function __construct(
        private readonly SalesScriptPlaySessionService $playSessionService,
    ) {}

    public function index(): Response
    {
        $scripts = SalesScript::query()
            ->with(['versions' => function ($q): void {
                $q->where('is_active', true)->whereNotNull('published_at')->orderByDesc('version_number');
            }])
            ->orderBy('title')
            ->get()
            ->map(function (SalesScript $script): array {
                $version = $script->versions->first();

                return [
                    'id' => $script->id,
                    'title' => $script->title,
                    'description' => $script->description,
                    'channel' => $script->channel,
                    'tags' => $script->tags ?? [],
                    'active_version' => $version ? [
                        'id' => $version->id,
                        'version_number' => $version->version_number,
                        'published_at' => $version->published_at?->toIso8601String(),
                    ] : null,
                ];
            });

        return Inertia::render('SalesScripts/Index', [
            'scripts' => $scripts,
        ]);
    }

    public function storeSession(StoreSalesScriptPlaySessionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        /** @var SalesScriptVersion $version */
        $version = SalesScriptVersion::query()->findOrFail($validated['sales_script_version_id']);

        try {
            $session = $this->playSessionService->start(
                $version,
                $request->user(),
                $validated['contractor_id'] ?? null,
                $validated['order_id'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['session' => $e->getMessage()]);
        }

        return to_route('scripts.sessions.show', $session);
    }

    public function showSession(Request $request, SalesScriptPlaySession $session): Response
    {
        $this->authorize('interact', $session);

        $session->load(['currentNode', 'version.script', 'events.reactionClass', 'events.node']);

        $current = $session->currentNode;
        $outgoing = [];
        if ($current !== null && ! $session->isComplete()) {
            foreach ($this->playSessionService->outgoingTransitions($current) as $t) {
                $rc = $t->reactionClass;
                $outgoing[] = [
                    'transition_id' => $t->id,
                    'sales_script_reaction_class_id' => $t->sales_script_reaction_class_id,
                    'label' => $rc ? $rc->label : 'Дальше',
                ];
            }
        }

        $eventTrail = $session->events->map(fn ($e): array => [
            'id' => $e->id,
            'type' => $e->type->value,
            'label' => match ($e->type) {
                SalesPlayEventType::EnteredNode => 'Шаг: '.($e->node?->client_key ?? '#'.$e->sales_script_node_id),
                SalesPlayEventType::RecordedReaction => 'Реакция: '.($e->reactionClass?->label ?? '—'),
                SalesPlayEventType::Completed => 'Завершено',
                default => $e->type->value,
            },
        ]);

        $reactionClasses = SalesScriptReactionClass::query()->orderBy('sort_order')->orderBy('label')->get(['id', 'key', 'label']);

        return Inertia::render('SalesScripts/Play', [
            'session' => [
                'id' => $session->id,
                'completed_at' => $session->completed_at?->toIso8601String(),
                'outcome' => $session->outcome?->value,
                'notes' => $session->notes,
                'script_title' => $session->version?->script?->title,
                'version_number' => $session->version?->version_number,
            ],
            'currentNode' => $current ? [
                'id' => $current->id,
                'kind' => $current->kind->value,
                'body' => $current->body,
                'hint' => $current->hint,
                'client_key' => $current->client_key,
            ] : null,
            'outgoingTransitions' => $outgoing,
            'mustComplete' => $current !== null && count($outgoing) === 0 && ! $session->isComplete(),
            'eventTrail' => $eventTrail,
            'outcomeOptions' => collect(SalesPlaySessionOutcome::cases())->map(fn (SalesPlaySessionOutcome $o): array => [
                'value' => $o->value,
                'label' => match ($o) {
                    SalesPlaySessionOutcome::NoContact => 'Не дозвонились / нет контакта',
                    SalesPlaySessionOutcome::Progress => 'Есть прогресс, продолжаем',
                    SalesPlaySessionOutcome::QuoteSent => 'Отправлено КП / ставка',
                    SalesPlaySessionOutcome::Won => 'Успех (сделка / договорённость)',
                    SalesPlaySessionOutcome::Lost => 'Отказ',
                    SalesPlaySessionOutcome::Postponed => 'Отложено',
                },
            ]),
            'reactionClasses' => $reactionClasses,
        ]);
    }

    public function advance(AdvanceSalesScriptPlaySessionRequest $request, SalesScriptPlaySession $session): RedirectResponse
    {
        $this->authorize('interact', $session);

        $validated = $request->validated();

        try {
            $this->playSessionService->advance(
                $session,
                $validated['sales_script_reaction_class_id'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['advance' => $e->getMessage()]);
        }

        return to_route('scripts.sessions.show', $session);
    }

    public function complete(CompleteSalesScriptPlaySessionRequest $request, SalesScriptPlaySession $session): RedirectResponse
    {
        $this->authorize('interact', $session);

        $validated = $request->validated();

        $outcome = SalesPlaySessionOutcome::from($validated['outcome']);

        try {
            $this->playSessionService->complete(
                $session,
                $outcome,
                $validated['primary_reaction_class_id'] ?? null,
                $validated['notes'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['complete' => $e->getMessage()]);
        }

        return to_route('scripts.index')->with('flash', [
            'type' => 'success',
            'message' => 'Сессия сохранена. Спасибо за разметку — это улучшает подсказки для команды.',
        ]);
    }
}
