<?php

namespace App\Http\Controllers;

use App\Enums\SalesPlayEventType;
use App\Enums\SalesPlaySessionOutcome;
use App\Enums\TrainerPeerReaction;
use App\Http\Requests\AdvanceSalesScriptPlaySessionRequest;
use App\Http\Requests\CompleteSalesScriptPlaySessionRequest;
use App\Http\Requests\StoreSalesScriptPlaySessionRequest;
use App\Http\Requests\StoreTrainerChatMessageRequest;
use App\Http\Requests\UpdateTrainerMessagePeerReactionRequest;
use App\Http\Requests\UpdateTrainerSessionMetaRequest;
use App\Models\SalesScript;
use App\Models\SalesScriptNode;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptReactionClass;
use App\Models\SalesScriptTrainerMessage;
use App\Models\SalesScriptVersion;
use App\Models\User;
use App\Services\SalesScripts\SalesScriptPlayPresentationService;
use App\Services\SalesScripts\SalesScriptPlaySessionService;
use App\Services\SalesScripts\TrainerAssistantAutoReactionService;
use App\Services\SalesScripts\TrainerCoachingHintService;
use App\Services\SalesScripts\TrainerDialogHintService;
use App\Services\SalesScripts\TrainerSalesBookBriefService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class SalesScriptController extends Controller
{
    public function __construct(
        private readonly SalesScriptPlaySessionService $playSessionService,
        private readonly SalesScriptPlayPresentationService $playPresentationService,
        private readonly TrainerDialogHintService $trainerDialogHintService,
        private readonly TrainerAssistantAutoReactionService $trainerAssistantAutoReactionService,
        private readonly TrainerSalesBookBriefService $trainerSalesBookBriefService,
        private readonly TrainerCoachingHintService $trainerCoachingHintService,
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

        $session->load(['currentNode', 'version.script', 'events.reactionClass', 'events.node', 'trainerMessages']);
        $current = $this->resolveCurrentNode($session);
        $session->load(['currentNode', 'version.script', 'events.reactionClass', 'events.node', 'trainerMessages']);
        $outgoing = [];
        if ($current !== null && ! $session->isComplete()) {
            foreach ($this->playSessionService->outgoingTransitions($current) as $t) {
                $rc = $t->reactionClass;
                $outgoing[] = [
                    'transition_id' => $t->id,
                    'sales_script_reaction_class_id' => $t->sales_script_reaction_class_id,
                    'customer_label' => $t->customer_label,
                    'label' => filled($t->customer_label)
                        ? (string) $t->customer_label
                        : ($rc ? $rc->label : 'Дальше'),
                ];
            }
        }

        $playPresentation = $this->playPresentationService->build($current);

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
        }

        return Inertia::render('SalesScripts/Play', [
            'playContext' => [
                'return' => $session->is_trainer ? 'trainer' : $request->session()->get('sales_script_play_return'),
                'trainer_profile' => $trainerProfile,
                'trainer_chat' => $trainerChat,
                'training_role_mode' => $session->training_role_mode ?: 'manager_seller',
                'trainer_contextual_hints' => $trainerContextualHints,
                'trainer_entry_preview' => $trainerEntryPreview,
            ],
            'session' => [
                'id' => $session->id,
                'completed_at' => $session->completed_at?->toIso8601String(),
                'outcome' => $session->outcome?->value,
                'notes' => $session->notes,
                'script_title' => $session->version?->script?->title,
                'version_number' => $session->version?->version_number,
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

        $history[] = [
            'role' => 'user',
            'content' => $userMessage,
            'at' => now()->toIso8601String(),
        ];
        $session->trainerMessages()->create([
            'user_id' => $request->user()?->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        $reply = $this->deepSeekTrainerReply(
            $profile,
            $session,
            $history,
            $request->user(),
            $userMessage,
        );

        $assistantMessage = $session->trainerMessages()->create([
            'user_id' => null,
            'role' => 'assistant',
            'content' => $reply,
        ]);

        $autoReaction = $this->trainerAssistantAutoReactionService->classify($session, $reply, $userMessage);
        $assistantMessage->update(['auto_peer_reaction' => $autoReaction]);

        $session->refresh();
        $session->load('trainerMessages');
        $lines = $this->trainerChatPayload($session->trainerMessages()->orderBy('id')->get());
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
            'coaching' => $coaching,
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
        $trainer_message->update([
            'peer_reaction' => $raw === null ? null : TrainerPeerReaction::from($raw),
        ]);
        $trainer_message->refresh();

        return response()->json([
            'id' => $trainer_message->id,
            'peer_reaction' => $trainer_message->peer_reaction?->value,
            'auto_peer_reaction' => $trainer_message->auto_peer_reaction?->value,
        ]);
    }

    /**
     * @param  Collection<int, SalesScriptTrainerMessage>  $messages
     * @return list<array{id:int,role:string,content:string,at:?string,peer_reaction:?string,auto_peer_reaction:?string}>
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
     * @param  array<string, mixed>  $profile
     * @param  list<array{role:string,content:string,at?:string}>  $history
     */
    private function deepSeekTrainerReply(
        array $profile,
        SalesScriptPlaySession $session,
        array $history,
        ?User $user,
        string $lastUserMessage,
    ): string {
        $apiKey = (string) config('ai.providers.deepseek.key');
        if ($apiKey === '') {
            return 'Не настроен DEEPSEEK_API_KEY. Пока тренировка недоступна.';
        }

        $session->loadMissing('version.script');

        $title = (string) ($profile['title'] ?? 'Покупатель');
        $context = (string) ($profile['context'] ?? 'Веди реалистичный диалог как клиент.');
        $scriptTitle = (string) ($session->version?->script?->title ?? 'Скрипт продаж');
        $managerAsBuyer = $session->training_role_mode === 'manager_buyer';

        $sharedTrainerSceneRules = "Общие правила тренировочной сцены:\n".
            "- Это одна непрерывная сцена переговоров; не сбрасывай контекст и не веди себя как при новом первом контакте, если диалог уже развёрнут.\n".
            "- Не повторяй дословно одно и то же возражение или вопрос, если на него уже ответили — двигай диалог вперёд.\n".
            "- Если собеседник повторяет вопрос — кратко уточни или дай новый угол, а не копируй предыдущую реплику.\n".
            "- Если в последних репликах уже зафиксированы конкретные договорённости (следующий шаг, срок, сумма, время созвона, явное согласие) — не разворачивай переговоры заново: дай короткий итог или заверши реплику без новых продажных циклов по уже закрытым вопросам.\n".
            "- Если собеседник явно завершает диалог (благодарность и стоп, «на этом достаточно», финальный тон согласия) — поддержи завершение, не уводи в новую воронку.\n";

        $sellerTrainerRules = "Как звучать в диалоге:\n".
            "- Ситуация — живой контакт менеджера с собеседником (часто первый или ранний); ты не знаешь заранее его полномочия и настрой — выясняй естественно.\n".
            "- Ориентир по продукту и отрасли — из названия сценария «{$scriptTitle}»; не выдумывай юридическое название своей компании, если его не назвали в переписке — можно «мы», «наша сторона», «наша компания».\n".
            "- Ни в коем случае не произноси вслух слова «профиль», «тренажёр», «сценарий обучения», не обращайся к собеседнику как к «игроку покупателя».\n".
            "- Ниже дано описание типичного поведения собеседника (для твоего понимания возражений) — это не то, что ты должен ему процитировать или озвучивать.\n";

        $buyerTrainerRules = "Как звучать в диалоге:\n".
            "- Ты обычный собеседник на стороне клиента; ниже — описание твоей роли для отработки (не озвучивай метки «профиль», «тренажёр»).\n";

        $systemPrompt = $managerAsBuyer
            ? "Ты — менеджер по продажам / представитель поставщика в учебном диалоге (письменная имитация звонка или переписки).\n".
                "Собеседник отвечает как представитель заказчика. Ориентир по теме разговора: «{$scriptTitle}».\n\n".
                $sellerTrainerRules.
                "\nТиповый портрет собеседника (внутренняя подсказка, не для цитирования): {$title}.\n".
                "Доп. контекст его роли (внутренняя подсказка): {$context}\n\n".
                "Правила реплик:\n".
                "- Только от лица продавца; реалистично и коротко (1–4 предложения).\n".
                "- Профессионально: уточняй потребность, работай с возражениями, предлагай следующий шаг без токсичности.\n".
                "- Не дави на мгновенное закрытие в первых репликах; если покупатель уже согласился на конкретный шаг — зафиксируй и не откатывай уже решённое.\n".
                "- Не раскрывай, что ты AI.\n\n".
                $sharedTrainerSceneRules
            : "Ты — клиент / заказчик в учебном диалоге.\n".
                "Менеджер (пользователь) тренируется с тобой. Тема сценария: «{$scriptTitle}».\n\n".
                $buyerTrainerRules.
                "\nТвоя роль: {$title}\n".
                "Контекст поведения: {$context}\n\n".
                "Правила реплик:\n".
                "- Только от лица клиента; реалистично и коротко (1–4 предложения).\n".
                "- Иногда задавай встречные вопросы.\n".
                "- Не раскрывай, что ты AI.\n".
                "- Оценивай предложения менеджера как в живом разговоре.\n\n".
                $sharedTrainerSceneRules;

        $extra = trim((string) ($session->trainer_assistant_instructions ?? ''));
        if ($extra !== '') {
            $systemPrompt .= "\n\nДополнительные указания к репликам:\n".$extra;
        }

        if ($user !== null) {
            $salesBookBrief = $this->trainerSalesBookBriefService->buildContextBlock(
                $user,
                $scriptTitle,
                $lastUserMessage,
                $history,
            );

            if (is_string($salesBookBrief) && $salesBookBrief !== '') {
                $systemPrompt .= "\n\n".$salesBookBrief;
            }
        }

        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
        ];

        foreach (array_slice($history, -20) as $item) {
            $messages[] = [
                'role' => $item['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => (string) ($item['content'] ?? ''),
            ];
        }

        try {
            $response = Http::timeout(45)
                ->withToken($apiKey)
                ->post('https://api.deepseek.com/chat/completions', [
                    'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
                    'temperature' => (float) env('DEEPSEEK_TRAINER_TEMPERATURE', 0.7),
                    'max_tokens' => 350,
                    'messages' => $messages,
                ])
                ->throw()
                ->json();

            $content = (string) data_get($response, 'choices.0.message.content', '');
            $content = trim($content);

            if ($content !== '') {
                return $content;
            }

            $scriptTitleTrim = trim($scriptTitle);

            return $managerAsBuyer
                ? ($scriptTitleTrim !== ''
                    ? 'Добрый день! Повторю короче: звоню впервые по теме «'.$scriptTitleTrim.'» — подскажите, удобно ли сейчас пару минут?'
                    : 'Добрый день! Повторю короче: звоню впервые — подскажите, удобно ли сейчас пару минут?')
                : 'Клиент задумался и просит уточнить детали.';
        } catch (\Throwable) {
            return $managerAsBuyer
                ? 'Сейчас не удалось получить ответ. Повторите сообщение или попробуйте ещё раз позже.'
                : 'Сейчас не удалось получить ответ клиента. Повторите сообщение еще раз.';
        }
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
            $this->playSessionService->complete(
                $session,
                $outcome,
                $validated['primary_reaction_class_id'] ?? null,
                $validated['notes'] ?? null,
            );
            if ($session->is_trainer) {
                $session->update([
                    'trainer_score' => $this->trainerScoreByOutcome($outcome),
                ]);
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

        return to_route('scripts.index')->with('flash', $flash);
    }

    private function trainerScoreByOutcome(SalesPlaySessionOutcome $outcome): int
    {
        return match ($outcome) {
            SalesPlaySessionOutcome::Won => 100,
            SalesPlaySessionOutcome::QuoteSent => 85,
            SalesPlaySessionOutcome::Progress => 70,
            SalesPlaySessionOutcome::Postponed => 55,
            SalesPlaySessionOutcome::NoContact => 40,
            SalesPlaySessionOutcome::Lost => 20,
        };
    }
}
