<?php

namespace App\Http\Controllers;

use App\Enums\SalesPlayEventType;
use App\Enums\SalesPlaySessionOutcome;
use App\Enums\TrainerPeerReaction;
use App\Http\Requests\AdvanceSalesScriptPlaySessionRequest;
use App\Http\Requests\CompleteSalesScriptPlaySessionRequest;
use App\Http\Requests\CreateSalesScriptLeadRequest;
use App\Http\Requests\LinkSalesScriptLeadRequest;
use App\Http\Requests\StoreSalesScriptPlaySessionRequest;
use App\Http\Requests\StoreTrainerChatMessageRequest;
use App\Http\Requests\UpdateTrainerMessagePeerReactionRequest;
use App\Http\Requests\UpdateTrainerSessionMetaRequest;
use App\Models\Lead;
use App\Models\SalesScript;
use App\Models\SalesScriptCaptureField;
use App\Models\SalesScriptNode;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptReactionClass;
use App\Models\SalesScriptTrainerMessage;
use App\Models\SalesScriptVersion;
use App\Services\Ai\AiInteractionRecorder;
use App\Services\SalesScripts\SalesScriptAnalyticsService;
use App\Services\SalesScripts\SalesScriptConversationGuidanceService;
use App\Services\SalesScripts\SalesScriptCrmActionService;
use App\Services\SalesScripts\SalesScriptLeadLinkService;
use App\Services\SalesScripts\SalesScriptNodeBodyResolver;
use App\Services\SalesScripts\SalesScriptPlayPresentationService;
use App\Services\SalesScripts\SalesScriptPlaySessionService;
use App\Services\SalesScripts\TrainerAssistantAutoReactionService;
use App\Services\SalesScripts\TrainerChatCompletionService;
use App\Services\SalesScripts\TrainerCoachingHintService;
use App\Services\SalesScripts\TrainerDialogHintService;
use App\Services\SalesScripts\TrainerGraphCoordinatorService;
use App\Services\SalesScripts\TrainerRubricService;
use App\Services\SalesScripts\TrainerScenarioGuidanceService;
use App\Services\SalesScripts\TrainerScoreCalculator;
use App\Support\AiChannel;
use App\Support\AiInteractionFeature;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class SalesScriptController extends Controller
{
    public function __construct(
        private readonly SalesScriptPlaySessionService $playSessionService,
        private readonly SalesScriptCrmActionService $crmActionService,
        private readonly SalesScriptLeadLinkService $leadLinkService,
        private readonly SalesScriptPlayPresentationService $playPresentationService,
        private readonly SalesScriptConversationGuidanceService $conversationGuidance,
        private readonly SalesScriptAnalyticsService $scriptAnalyticsService,
        private readonly SalesScriptNodeBodyResolver $nodeBodyResolver,
        private readonly TrainerDialogHintService $trainerDialogHintService,
        private readonly TrainerAssistantAutoReactionService $trainerAssistantAutoReactionService,
        private readonly TrainerCoachingHintService $trainerCoachingHintService,
        private readonly TrainerGraphCoordinatorService $trainerGraphCoordinatorService,
        private readonly TrainerRubricService $trainerRubricService,
        private readonly TrainerScenarioGuidanceService $trainerScenarioGuidanceService,
        private readonly TrainerChatCompletionService $trainerChatCompletionService,
        private readonly TrainerScoreCalculator $trainerScoreCalculator,
        private readonly AiInteractionRecorder $aiInteractionRecorder,
    ) {}

    public function index(Request $request): Response
    {
        $canManage = RoleAccess::canManageSalesScripts($request->user());

        $scripts = SalesScript::query()
            ->with(['versions' => function ($q) use ($canManage): void {
                $q->orderByDesc('version_number');
                if (! $canManage) {
                    $q->where('is_active', true)->whereNotNull('published_at');
                }
            }])
            ->orderBy('title')
            ->get()
            ->map(function (SalesScript $script) use ($canManage): array {
                $publishedVersion = $script->versions->first(
                    fn (SalesScriptVersion $version): bool => $version->is_active && $version->published_at !== null,
                );
                $latestEditorVersion = $canManage ? $script->versions->first() : null;

                return [
                    'id' => $script->id,
                    'title' => $script->title,
                    'description' => $script->description,
                    'channel' => $script->channel,
                    'tags' => $script->tags ?? [],
                    'active_version' => $publishedVersion ? [
                        'id' => $publishedVersion->id,
                        'version_number' => $publishedVersion->version_number,
                        'published_at' => $publishedVersion->published_at?->toIso8601String(),
                    ] : null,
                    'latest_editor_version' => $latestEditorVersion ? [
                        'id' => $latestEditorVersion->id,
                        'version_number' => $latestEditorVersion->version_number,
                    ] : null,
                ];
            });

        $latestGraphVersionId = null;
        if ($canManage) {
            $latestGraphVersionId = SalesScriptVersion::query()
                ->orderByDesc('updated_at')
                ->value('id');
        }

        return Inertia::render('SalesScripts/Index', [
            'scripts' => $scripts,
            'latestGraphVersionId' => $latestGraphVersionId,
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
                $validated['lead_id'] ?? null,
                $validated['order_id'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['session' => $e->getMessage()]);
        }

        if (($validated['return_to'] ?? null) === 'trainer') {
            $session->update([
                'is_trainer' => true,
                'trainer_profile_key' => $validated['trainer_profile_key'] ?? null,
                'trainer_profile_title' => $validated['trainer_profile_title'] ?? null,
                'trainer_profile_context' => $validated['trainer_profile_context'] ?? null,
                'training_role_mode' => $validated['training_role_mode'] ?? 'manager_seller',
            ]);

            $request->session()->put('sales_script_play_return', 'trainer');
            $request->session()->put('sales_script_play_trainer_profile', [
                'key' => $validated['trainer_profile_key'] ?? null,
                'title' => $validated['trainer_profile_title'] ?? null,
                'context' => $validated['trainer_profile_context'] ?? null,
                'training_role_mode' => $validated['training_role_mode'] ?? 'manager_seller',
            ]);
        } else {
            $session->update([
                'is_trainer' => false,
                'trainer_profile_key' => null,
                'trainer_profile_title' => null,
                'trainer_profile_context' => null,
                'training_role_mode' => 'manager_seller',
            ]);
            $request->session()->forget('sales_script_play_return');
            $request->session()->forget('sales_script_play_trainer_profile');
        }

        return to_route('scripts.sessions.show', $session);
    }

    public function showSession(Request $request, SalesScriptPlaySession $sales_script_play_session): Response
    {
        $session = $sales_script_play_session;
        $this->authorize('interact', $session);

        $session->load(['currentNode', 'version.script', 'events.reactionClass', 'events.node', 'trainerMessages', 'fieldValues.captureField', 'lead']);
        $current = $this->resolveCurrentNode($session);
        $session->load(['currentNode', 'version.script', 'events.reactionClass', 'events.node', 'trainerMessages', 'fieldValues.captureField', 'lead']);
        $outgoing = [];
        if ($current !== null && ! $session->isComplete()) {
            foreach ($this->playSessionService->outgoingTransitions($current) as $t) {
                $rc = $t->reactionClass;
                $outgoing[] = [
                    'transition_id' => $t->id,
                    'sales_script_reaction_class_id' => $t->sales_script_reaction_class_id,
                    'target_type' => $t->target_type ?? 'node',
                    'target_script_title' => $t->targetVersion?->script?->title,
                    'customer_label' => $t->customer_label,
                    'label' => filled($t->customer_label)
                        ? (string) $t->customer_label
                        : ($rc ? $rc->label : 'Дальше'),
                ];
            }
        }

        $displayNode = $current !== null ? $this->nodeBodyResolver->nodeForDisplay($current, $session) : null;
        $playPresentation = $this->playPresentationService->build(
            $displayNode,
            $this->sessionFieldValuesByCode($session),
            $this->captureFieldLabelsByCode(),
        );

        $reactionIds = collect($playPresentation['choices'] ?? [])
            ->pluck('sales_script_reaction_class_id')
            ->filter()
            ->all();
        $statsHints = ($current !== null && ! $session->is_trainer && ! $session->isComplete())
            ? $this->scriptAnalyticsService->playChoiceHints(
                (int) $session->sales_script_version_id,
                (int) $current->id,
                $reactionIds,
            )
            : [];

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

        $trainerProfile = $session->is_trainer
            ? [
                'key' => $session->trainer_profile_key,
                'title' => $session->trainer_profile_title,
                'context' => $session->trainer_profile_context,
            ]
            : $request->session()->get('sales_script_play_trainer_profile');

        if ($session->is_trainer && ! $session->isComplete()) {
            $this->ensureTrainerSellerOpensWhenUserIsBuyer($session);
        }

        $trainerChat = $this->trainerChatPayload(
            $session->trainerMessages()->orderBy('id')->get()
        );

        $trainerContextualHints = [];
        $trainerEntryPreview = null;
        $trainerStepHints = [];
        if ($session->is_trainer && $this->includeTrainerScenarioLexicalHints($session)) {
            $trainerContextualHints = $this->trainerDialogHintService->contextualNodeHints(
                (int) $session->sales_script_version_id,
                $current?->id,
                $session->trainerMessages,
                6,
            );
            $trainerEntryPreview = $this->trainerDialogHintService->entryNodePreview(
                (int) $session->sales_script_version_id,
                $session->version?->entry_node_key,
            );
            $trainerStepHints = $this->trainerScenarioGuidanceService->build($current, $playPresentation);
        }

        return Inertia::render('SalesScripts/Play', [
            'playContext' => [
                'return' => $session->is_trainer ? 'trainer' : $request->session()->get('sales_script_play_return'),
                'trainer_profile' => $trainerProfile,
                'trainer_chat' => $trainerChat,
                'training_role_mode' => $session->training_role_mode ?: 'manager_seller',
                'trainer_contextual_hints' => $trainerContextualHints,
                'trainer_entry_preview' => $trainerEntryPreview,
                'trainer_step_hints' => $trainerStepHints,
                'trainer_rubric' => $session->is_trainer ? $this->trainerRubricService->forSession($session) : null,
            ],
            'session' => [
                'id' => $session->id,
                'completed_at' => $session->completed_at?->toIso8601String(),
                'outcome' => $session->outcome?->value,
                'notes' => $session->notes,
                'lead_id' => $session->lead_id,
                'order_id' => $session->order_id,
                'crm_synced_at' => $session->crm_synced_at?->toIso8601String(),
                'script_title' => $session->version?->script?->title,
                'version_number' => $session->version?->version_number,
                'return_stack_depth' => count((array) ($session->return_stack ?? [])),
                'return_to_script_title' => $this->returnToScriptTitle($session),
                'trainer_assistant_instructions' => $session->trainer_assistant_instructions,
                'trainer_dialog_quality' => $session->trainer_dialog_quality?->value,
            ],
            'currentNode' => $current ? [
                'id' => $current->id,
                'kind' => $current->kind->value,
                'body' => $current->body,
                'hint' => $current->hint,
                'client_key' => $current->client_key,
            ] : null,
            'outgoingTransitions' => $outgoing,
            'playPresentation' => $playPresentation,
            'dialogState' => $this->conversationGuidance->stateForSession($session, $current),
            'statsHints' => $statsHints,
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
            'capturedFields' => $this->serializeCapturedFieldsSummary($session),
            'crmLinking' => [
                'available' => RoleAccess::hasVisibilityArea(
                    RoleAccess::userVisibilityAreas($request->user()),
                    'leads',
                ),
                'linked_lead' => $session->lead ? [
                    'id' => $session->lead->id,
                    'number' => $session->lead->number,
                    'title' => $session->lead->title,
                    'show_url' => route('leads.show', $session->lead),
                ] : null,
            ],
        ]);
    }

    public function trainerMessage(StoreTrainerChatMessageRequest $request, SalesScriptPlaySession $sales_script_play_session): JsonResponse
    {
        $session = $sales_script_play_session;
        $this->authorize('interact', $session);

        abort_unless($session->is_trainer || $request->session()->get('sales_script_play_return') === 'trainer', 403);

        $validated = $request->validated();

        $profile = [
            'key' => $session->trainer_profile_key,
            'title' => $session->trainer_profile_title,
            'context' => $session->trainer_profile_context,
        ];
        $history = $session->trainerMessages()
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (SalesScriptTrainerMessage $message): array => [
                'role' => $message->role,
                'content' => $message->content,
                'at' => $message->created_at?->toIso8601String(),
            ])
            ->all();

        $userMessage = trim((string) $validated['message']);
        if ($userMessage === '') {
            return response()->json(['message' => 'Пустое сообщение.'], 422);
        }

        $session->refresh();
        $managerNode = $this->resolveCurrentNode($session);
        if ($managerNode !== null) {
            $this->playSessionService->saveFieldValues(
                $session,
                $managerNode,
                $validated['field_values'] ?? [],
            );
        }

        $history[] = [
            'role' => 'user',
            'content' => $userMessage,
            'at' => now()->toIso8601String(),
        ];
        $session->trainerMessages()->create([
            'user_id' => $request->user()?->id,
            'sales_script_node_id' => $managerNode?->id,
            'step_key' => $managerNode?->client_key,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        $this->trainerGraphCoordinatorService->afterManagerMessage($session);

        $session->refresh();
        $session->loadMissing(['currentNode', 'version.script', 'events.reactionClass', 'events.node']);
        $graphBeforeReply = $this->buildTrainerGraphPayload($session);
        $playPresentation = $graphBeforeReply['play_presentation'];

        $startedAt = microtime(true);
        $completion = $this->trainerChatCompletionService->replyForTrainerSession(
            $session,
            $profile,
            $history,
            $userMessage,
            $request->user(),
            $playPresentation,
        );
        $reply = $completion['reply'];

        if ($request->user() !== null) {
            $this->aiInteractionRecorder->recordConversationTurn(
                $request->user(),
                AiInteractionFeature::Trainer,
                AiChannel::ExternalLarge,
                $completion['outcome'],
                $userMessage,
                $reply,
                0,
                [],
                (int) round((microtime(true) - $startedAt) * 1000),
                metadata: [
                    'session_id' => $session->id,
                    'training_role_mode' => $session->training_role_mode ?: 'manager_seller',
                    'step_key' => $playPresentation['step_key'] ?? null,
                ],
            );
        }

        $assistantMessage = $session->trainerMessages()->create([
            'user_id' => null,
            'sales_script_node_id' => $graphBeforeReply['current_node']['id'] ?? null,
            'step_key' => $playPresentation['step_key'] ?? ($graphBeforeReply['current_node']['client_key'] ?? null),
            'role' => 'assistant',
            'content' => $reply,
        ]);

        $autoReaction = $this->trainerAssistantAutoReactionService->classify($session, $reply, $userMessage);
        $assistantMessage->update(['auto_peer_reaction' => $autoReaction]);

        $this->trainerGraphCoordinatorService->afterClientReply($session, $reply);

        $session->refresh();
        $session->load(['trainerMessages', 'fieldValues.captureField']);
        $lines = $this->trainerChatPayload($session->trainerMessages()->orderBy('id')->get());
        $graphPayload = $this->buildTrainerGraphPayload($session);
        $resolvedCurrent = $this->resolveCurrentNode($session);
        $contextualHints = $this->includeTrainerScenarioLexicalHints($session)
            ? $this->trainerDialogHintService->contextualNodeHints(
                (int) $session->sales_script_version_id,
                $resolvedCurrent?->id,
                $session->trainerMessages()->orderBy('id')->get(),
                6,
            )
            : [];

        $coaching = $this->trainerCoachingHintService->build(
            $session,
            $session->trainerMessages()->orderBy('id')->get(),
            $resolvedCurrent?->id,
        );

        return response()->json([
            'reply' => $reply,
            'history' => array_slice($lines, -40),
            'contextual_hints' => $contextualHints,
            'trainer_step_hints' => $graphPayload['trainer_step_hints'],
            'coaching' => $coaching,
            'current_node' => $graphPayload['current_node'],
            'play_presentation' => $graphPayload['play_presentation'],
            'outgoing_transitions' => $graphPayload['outgoing_transitions'],
            'event_trail' => $graphPayload['event_trail'],
            'must_complete' => $graphPayload['must_complete'],
            'trainer_rubric' => $this->trainerRubricService->forSession($session),
            'captured_fields' => $this->serializeCapturedFieldsSummary($session),
        ]);
    }

    public function updateTrainerMessagePeerReaction(
        UpdateTrainerMessagePeerReactionRequest $request,
        SalesScriptPlaySession $sales_script_play_session,
        SalesScriptTrainerMessage $trainer_message,
    ): JsonResponse {
        $session = $sales_script_play_session;
        $this->authorize('interact', $session);

        abort_unless($session->is_trainer || $request->session()->get('sales_script_play_return') === 'trainer', 403);
        abort_if((int) $trainer_message->sales_script_play_session_id !== (int) $session->id, 404);

        if ($session->isComplete()) {
            return response()->json(['message' => 'Сессия уже завершена.'], 422);
        }

        if ($trainer_message->role !== 'assistant') {
            return response()->json(['message' => 'Оценку можно поставить только на реплику ассистента.'], 422);
        }

        $raw = $request->validated('peer_reaction');
        $feedbackTags = $request->validated('feedback_tags', []);
        $trainer_message->update([
            'peer_reaction' => $raw === null ? null : TrainerPeerReaction::from($raw),
            'feedback_tags' => $feedbackTags === [] ? null : $feedbackTags,
        ]);
        $trainer_message->refresh();
        $session->load(['trainerMessages', 'fieldValues.captureField', 'events.node', 'version.script']);

        return response()->json([
            'id' => $trainer_message->id,
            'peer_reaction' => $trainer_message->peer_reaction?->value,
            'auto_peer_reaction' => $trainer_message->auto_peer_reaction?->value,
            'feedback_tags' => $trainer_message->feedback_tags ?? [],
            'trainer_rubric' => $this->trainerRubricService->forSession($session),
        ]);
    }

    /**
     * @param  Collection<int, SalesScriptTrainerMessage>  $messages
     * @return list<array{id:int,role:string,content:string,at:?string,peer_reaction:?string,auto_peer_reaction:?string,feedback_tags:list<string>,sales_script_node_id:?int,step_key:?string}>
     */
    private function trainerChatPayload(Collection $messages): array
    {
        return $messages->map(fn (SalesScriptTrainerMessage $message): array => [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'at' => $message->created_at?->toIso8601String(),
            'peer_reaction' => $message->peer_reaction?->value,
            'auto_peer_reaction' => $message->auto_peer_reaction?->value,
            'feedback_tags' => array_values(array_filter(
                (array) ($message->feedback_tags ?? []),
                fn (mixed $tag): bool => is_string($tag) && $tag !== '',
            )),
            'sales_script_node_id' => $message->sales_script_node_id,
            'step_key' => $message->step_key,
        ])->values()->all();
    }

    /**
     * В режиме «пользователь — покупатель, ассистент — продавец» первое слово за менеджером (приветствие в чате).
     * Имитация первого контакта; без мета-слов («профиль», «тренажёр»).
     */
    private function ensureTrainerSellerOpensWhenUserIsBuyer(SalesScriptPlaySession $session): void
    {
        if (($session->training_role_mode ?: 'manager_seller') !== 'manager_buyer') {
            return;
        }

        if ($session->trainerMessages()->exists()) {
            return;
        }

        $session->loadMissing('version.script');
        $scriptTitle = trim((string) ($session->version?->script?->title ?? ''));
        $line = $scriptTitle !== ''
            ? 'Добрый день! Я менеджер по продажам, мы с вами ещё не общались — звоню познакомиться и коротко обсудить возможное сотрудничество по теме «'
            .$scriptTitle
            .'». Подскажите, я попал по адресу — по этому направлению с вами можно говорить?'
            : 'Добрый день! Я менеджер по продажам, звоню впервые познакомиться. Подскажите, с кем я разговариваю и удобно ли уделить пару минут?';

        $assistantMessage = $session->trainerMessages()->create([
            'user_id' => null,
            'sales_script_node_id' => $session->current_node_id,
            'step_key' => $session->currentNode?->client_key,
            'role' => 'assistant',
            'content' => $line,
        ]);

        $autoReaction = $this->trainerAssistantAutoReactionService->classify($session, $line, '');
        $assistantMessage->update(['auto_peer_reaction' => $autoReaction]);

        $session->unsetRelation('trainerMessages');
    }

    public function updateTrainerMeta(
        UpdateTrainerSessionMetaRequest $request,
        SalesScriptPlaySession $sales_script_play_session,
    ): JsonResponse {
        $session = $sales_script_play_session;
        $this->authorize('interact', $session);

        abort_unless($session->is_trainer || $request->session()->get('sales_script_play_return') === 'trainer', 403);

        if ($session->isComplete()) {
            return response()->json(['message' => 'Сессия уже завершена.'], 422);
        }

        $validated = $request->validated();
        $updates = [];

        if (array_key_exists('trainer_assistant_instructions', $validated)) {
            $raw = $validated['trainer_assistant_instructions'];
            $updates['trainer_assistant_instructions'] = ($raw === null || $raw === '') ? null : $raw;
        }

        if (array_key_exists('trainer_dialog_quality', $validated)) {
            $updates['trainer_dialog_quality'] = $validated['trainer_dialog_quality'];
        }

        if ($updates === []) {
            return response()->json(['message' => 'Нет данных для сохранения.'], 422);
        }

        $session->update($updates);
        $session->refresh();

        return response()->json([
            'trainer_assistant_instructions' => $session->trainer_assistant_instructions,
            'trainer_dialog_quality' => $session->trainer_dialog_quality?->value,
        ]);
    }

    /**
     * @return array{
     *     current_node: array<string, mixed>|null,
     *     play_presentation: array<string, mixed>,
     *     outgoing_transitions: list<array<string, mixed>>,
     *     event_trail: list<array<string, mixed>>,
     *     trainer_step_hints: list<array<string, mixed>>,
     *     must_complete: bool
     * }
     */
    private function buildTrainerGraphPayload(SalesScriptPlaySession $session): array
    {
        $session->loadMissing(['currentNode', 'events.reactionClass', 'events.node']);
        $current = $this->resolveCurrentNode($session);
        $playPresentation = $this->playPresentationService->build(
            $current,
            $this->sessionFieldValuesByCode($session),
            $this->captureFieldLabelsByCode(),
        );
        $playPresentation['capture_fields'] = $this->captureFieldsForNode($current, $session);

        $outgoing = [];
        if ($current !== null && ! $session->isComplete()) {
            foreach ($this->playSessionService->outgoingTransitions($current) as $t) {
                $rc = $t->reactionClass;
                $outgoing[] = [
                    'transition_id' => $t->id,
                    'sales_script_reaction_class_id' => $t->sales_script_reaction_class_id,
                    'target_type' => $t->target_type ?? 'node',
                    'target_script_title' => $t->targetVersion?->script?->title,
                    'customer_label' => $t->customer_label,
                    'label' => filled($t->customer_label)
                        ? (string) $t->customer_label
                        : ($rc ? $rc->label : 'Дальше'),
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
        ])->values()->all();

        return [
            'current_node' => $current ? [
                'id' => $current->id,
                'kind' => $current->kind->value,
                'body' => $current->body,
                'hint' => $current->hint,
                'client_key' => $current->client_key,
            ] : null,
            'play_presentation' => $playPresentation,
            'outgoing_transitions' => $outgoing,
            'event_trail' => $eventTrail,
            'trainer_step_hints' => $this->trainerScenarioGuidanceService->build($current, $playPresentation),
            'must_complete' => $current !== null && count($outgoing) === 0 && ! $session->isComplete(),
            'trainer_rubric' => $session->is_trainer ? $this->trainerRubricService->forSession($session) : null,
        ];
    }

    /**
     * Подсказки по узлам сценария (текст продавца) не показываем, когда пользователь в роли покупателя.
     */
    private function includeTrainerScenarioLexicalHints(SalesScriptPlaySession $session): bool
    {
        return ($session->training_role_mode ?: 'manager_seller') !== 'manager_buyer';
    }

    private function restoreMissingCurrentNode(SalesScriptPlaySession $session): void
    {
        if ($session->isComplete()) {
            return;
        }

        if ($session->currentNode !== null) {
            return;
        }

        if ($session->current_node_id !== null) {
            $directNode = SalesScriptNode::query()->find($session->current_node_id);
            if ($directNode !== null) {
                return;
            }
        }

        $version = $session->version;
        $entryNodeKey = $version?->entry_node_key;
        if ($version === null || $entryNodeKey === null || $entryNodeKey === '') {
            return;
        }

        $entryNode = $version->nodes()->where('client_key', $entryNodeKey)->first();
        if ($entryNode === null) {
            return;
        }

        $session->update([
            'current_node_id' => $entryNode->id,
        ]);
    }

    private function resolveCurrentNode(SalesScriptPlaySession $session): ?SalesScriptNode
    {
        if ($session->isComplete()) {
            return $session->currentNode;
        }

        if ($session->currentNode !== null) {
            return $session->currentNode;
        }

        $resolved = null;

        if ($session->current_node_id !== null) {
            $resolved = SalesScriptNode::query()->find($session->current_node_id);
        }

        if ($resolved === null) {
            $lastEnteredEvent = $session->events
                ->where('type', SalesPlayEventType::EnteredNode)
                ->sortByDesc('id')
                ->first();

            if ($lastEnteredEvent?->sales_script_node_id !== null) {
                $resolved = SalesScriptNode::query()->find($lastEnteredEvent->sales_script_node_id);
            }
        }

        if ($resolved === null) {
            $this->restoreMissingCurrentNode($session);
            $session->refresh();
            $resolved = $session->currentNode;

            if ($resolved === null && $session->current_node_id !== null) {
                $resolved = SalesScriptNode::query()->find($session->current_node_id);
            }
        }

        if ($resolved !== null && (int) $session->current_node_id !== (int) $resolved->id) {
            $session->update([
                'current_node_id' => $resolved->id,
            ]);
            $session->setRelation('currentNode', $resolved);
        }

        return $resolved;
    }

    public function advance(
        AdvanceSalesScriptPlaySessionRequest $request,
        SalesScriptPlaySession $sales_script_play_session,
    ): RedirectResponse {
        $session = $sales_script_play_session;
        $this->authorize('interact', $session);

        $validated = $request->validated();

        try {
            $session->loadMissing('currentNode');
            $current = $session->currentNode;
            if ($current !== null) {
                $this->playSessionService->saveFieldValues(
                    $session,
                    $current,
                    $validated['field_values'] ?? [],
                );
            }

            $reactionId = $validated['sales_script_reaction_class_id'] ?? null;
            $compound = (bool) ($validated['compound'] ?? false);

            if ($compound && $reactionId !== null) {
                $this->playSessionService->advanceCompound($session, $reactionId);
            } else {
                $this->playSessionService->advance($session, $reactionId);
            }
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['advance' => $e->getMessage()]);
        }

        return to_route('scripts.sessions.show', $session);
    }

    public function complete(
        CompleteSalesScriptPlaySessionRequest $request,
        SalesScriptPlaySession $sales_script_play_session,
    ): RedirectResponse {
        $session = $sales_script_play_session;
        $this->authorize('interact', $session);

        $validated = $request->validated();

        $outcome = SalesPlaySessionOutcome::from($validated['outcome']);

        try {
            $session = $this->playSessionService->complete(
                $session,
                $outcome,
                $validated['primary_reaction_class_id'] ?? null,
                $validated['notes'] ?? null,
                $validated['lead_id'] ?? null,
                $validated['order_id'] ?? null,
            );
            if ($session->is_trainer) {
                $session->load('trainerMessages');
                $session->update([
                    'trainer_score' => $this->trainerScoreCalculator->calculate($session, $outcome),
                ]);
                $session->refresh();
            }
            if ($session->lead_id !== null || $session->order_id !== null) {
                $this->crmActionService->syncAfterCompletion($session);
            }
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['complete' => $e->getMessage()]);
        }

        $flash = [
            'type' => 'success',
            'message' => 'Сессия сохранена. Спасибо за разметку — это улучшает подсказки для команды.',
        ];

        $returnToTrainer = $request->session()->pull('sales_script_play_return') === 'trainer' || $session->is_trainer;
        if ($returnToTrainer) {
            return to_route('sales-assistant.trainer')->with('flash', $flash);
        }

        return to_route('scripts.sessions.show', $session)->with('flash', $flash);
    }

    public function searchLeads(Request $request, SalesScriptPlaySession $sales_script_play_session): JsonResponse
    {
        $session = $sales_script_play_session;
        $this->authorize('interact', $session);
        abort_unless(
            RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($request->user()), 'leads'),
            403,
        );

        return response()->json([
            'rows' => $this->leadLinkService->search(
                $request->user(),
                (string) $request->query('q', ''),
            ),
        ]);
    }

    public function linkLead(
        LinkSalesScriptLeadRequest $request,
        SalesScriptPlaySession $sales_script_play_session,
    ): RedirectResponse {
        $session = $sales_script_play_session;
        $this->authorize('interact', $session);

        try {
            $lead = Lead::query()->findOrFail((int) $request->validated('lead_id'));
            $this->leadLinkService->link($session, $lead, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['lead_link' => $exception->getMessage()]);
        }

        return to_route('scripts.sessions.show', $session)
            ->with('flash', ['type' => 'success', 'message' => 'Разговор связан с лидом. Итог добавлен в CRM.']);
    }

    public function createLead(
        CreateSalesScriptLeadRequest $request,
        SalesScriptPlaySession $sales_script_play_session,
    ): RedirectResponse {
        $session = $sales_script_play_session;
        $this->authorize('interact', $session);

        try {
            $lead = $this->leadLinkService->create(
                $session,
                $request->user(),
                $request->validated('title'),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['lead_create' => $exception->getMessage()]);
        }

        return to_route('scripts.sessions.show', $session)
            ->with('flash', [
                'type' => 'success',
                'message' => 'Создан лид '.$lead->number.'. Итог разговора добавлен в CRM.',
            ]);
    }

    /**
     * @return array<string, string>
     */
    private function sessionFieldValuesByCode(SalesScriptPlaySession $session): array
    {
        $session->loadMissing('fieldValues.captureField');

        $values = [];
        foreach ($session->fieldValues as $fieldValue) {
            $code = $fieldValue->captureField?->code;
            if ($code === null || $code === '') {
                continue;
            }

            $values[$code] = (string) $fieldValue->value;
        }

        return $values;
    }

    /**
     * @return array<string, string>
     */
    private function captureFieldLabelsByCode(): array
    {
        return SalesScriptCaptureField::query()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->pluck('label', 'code')
            ->all();
    }

    /**
     * @return list<array{code:string,label:string,value:string}>
     */
    private function captureFieldsForNode(?SalesScriptNode $node, SalesScriptPlaySession $session): array
    {
        $codes = array_values(array_filter(
            (array) ($node?->capture_field_codes ?? []),
            fn (mixed $code): bool => is_string($code) && $code !== '',
        ));

        if ($codes === []) {
            return [];
        }

        $labels = $this->captureFieldLabelsByCode();
        $values = $this->sessionFieldValuesByCode($session);

        return collect($codes)
            ->unique()
            ->map(fn (string $code): array => [
                'code' => $code,
                'label' => (string) ($labels[$code] ?? $code),
                'value' => (string) ($values[$code] ?? ''),
            ])
            ->values()
            ->all();
    }

    private function returnToScriptTitle(SalesScriptPlaySession $session): ?string
    {
        $stack = $session->return_stack;
        if (! is_array($stack) || $stack === []) {
            return null;
        }

        $frame = $stack[array_key_last($stack)];
        if (! is_array($frame)) {
            return null;
        }

        $versionId = (int) ($frame['return_sales_script_version_id'] ?? 0);
        if ($versionId <= 0) {
            return null;
        }

        return SalesScriptVersion::query()
            ->with('script')
            ->find($versionId)
            ?->script
            ?->title;
    }

    /**
     * @return list<array{code: string, label: string, value: string}>
     */
    private function serializeCapturedFieldsSummary(SalesScriptPlaySession $session): array
    {
        $session->loadMissing('fieldValues.captureField');

        return $session->fieldValues
            ->map(fn ($fieldValue): array => [
                'code' => (string) ($fieldValue->captureField?->code ?? ''),
                'label' => (string) ($fieldValue->captureField?->label ?? $fieldValue->captureField?->code ?? ''),
                'value' => (string) $fieldValue->value,
            ])
            ->filter(fn (array $row): bool => $row['code'] !== '')
            ->values()
            ->all();
    }
}
