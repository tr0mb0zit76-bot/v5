<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Contractor;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\User;
use App\Services\CabinetNotifier;
use App\Services\ExternalUsers\CounterpartyConversationService;
use App\Services\MessengerService;
use App\Support\CounterpartyOrderAccess;
use App\Support\CounterpartyPartyResolver;
use App\Support\ExternalParty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MessengerController extends Controller
{
    public function __construct(
        private readonly MessengerService $messengerService,
        private readonly CounterpartyConversationService $counterpartyConversationService,
        private readonly CounterpartyOrderAccess $counterpartyOrderAccess,
        private readonly CabinetNotifier $cabinetNotifier,
    ) {}

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        if (! Schema::hasTable('conversations')) {
            return response()->json(['unread_count' => 0]);
        }

        return response()->json([
            'unread_count' => $this->messengerService->totalUnreadFor($user),
        ]);
    }

    public function colleagues(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        if (! Schema::hasTable('users')) {
            return response()->json(['users' => []]);
        }

        if ($user->isExternal()) {
            return response()->json([
                'users' => $this->staffContactsForExternalUser($user),
            ]);
        }

        $hasPhoneColumn = Schema::hasColumn('users', 'phone');
        $columns = array_values(array_filter([
            'id',
            'name',
            'email',
            $hasPhoneColumn ? 'phone' : null,
        ]));

        $users = User::query()
            ->where('id', '!=', $user->id)
            ->when(Schema::hasColumn('users', 'is_active'), fn ($q) => $q->where('is_active', true))
            ->when(
                Schema::hasColumn('users', 'is_external') && $user->isExternal(),
                fn ($q) => $q->whereRaw('1 = 0'),
            )
            ->when(
                Schema::hasColumn('users', 'is_external') && ! $user->isExternal(),
                fn ($q) => $q->where(function ($query): void {
                    $query->where('is_external', false)->orWhereNull('is_external');
                }),
            )
            ->where(function ($query): void {
                $query->whereNull('name')
                    ->orWhereRaw('lower(name) != ?', ['cursor']);
            })
            ->where(function ($query): void {
                $query->whereNull('email')
                    ->orWhereRaw('lower(email) not like ?', ['cursor@%']);
            })
            ->orderBy('name')
            ->limit(100)
            ->get($columns);

        return response()->json([
            'users' => $users->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $hasPhoneColumn ? $u->phone : null,
            ]),
        ]);
    }

    public function documentChips(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        if (! Schema::hasTable('order_documents')) {
            return response()->json(['documents' => []]);
        }

        if ($user->isExternal()) {
            return response()->json(['documents' => []]);
        }

        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        return response()->json([
            'documents' => $this->messengerService->orderDocumentsForChips(
                $user,
                $validated['q'] ?? null,
            ),
        ]);
    }

    public function counterpartyContacts(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->isExternal(), 403);

        if (! Schema::hasTable('conversations') || ! Schema::hasColumn('conversations', 'channel')) {
            return response()->json(['contacts' => []]);
        }

        $conversations = $user->conversations()
            ->where('channel', 'counterparty')
            ->with(['participants', 'contractor:id,name'])
            ->orderByDesc('conversations.updated_at')
            ->limit(50)
            ->get();

        $contacts = $conversations->map(function (Conversation $conversation): ?array {
            $external = $this->counterpartyConversationService->externalParticipant($conversation);

            if ($external === null) {
                return null;
            }

            return [
                'user_id' => $external->id,
                'name' => $external->name,
                'email' => $external->email,
                'phone' => Schema::hasColumn('users', 'phone') ? $external->phone : null,
                'contractor_id' => $conversation->contractor_id,
                'contractor_name' => $conversation->contractor?->name,
                'external_party' => $conversation->external_party,
                'conversation_id' => $conversation->id,
            ];
        })->filter()->values();

        return response()->json(['contacts' => $contacts]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        if (! Schema::hasTable('conversations')) {
            return response()->json(['conversations' => [], 'unread_count' => 0]);
        }

        $validated = $request->validate([
            'channel' => ['sometimes', 'nullable', 'string', Rule::in(['internal', 'counterparty'])],
        ]);

        $query = $user->conversations()
            ->with(['latestMessage.author:id,name', 'contractor:id,name'])
            ->orderByDesc('conversations.updated_at');

        if (
            filled($validated['channel'] ?? null)
            && Schema::hasColumn('conversations', 'channel')
        ) {
            $query->where('channel', $validated['channel']);
        }

        if ($user->isExternal() && Schema::hasColumn('conversations', 'channel')) {
            $query->where('channel', 'counterparty');
        }

        $items = $query
            ->get()
            ->map(fn (Conversation $c): array => $this->serializeConversation($c, $user));

        return response()->json([
            'conversations' => $items,
            'unread_count' => $this->messengerService->totalUnreadFor($user),
        ]);
    }

    public function openCounterparty(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->isExternal(), 403);

        $validated = $request->validate([
            'contractor_id' => ['required', 'integer', 'exists:contractors,id'],
            'external_party' => ['required', 'string', Rule::in(ExternalParty::values())],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ]);

        $contractor = Contractor::query()->findOrFail($validated['contractor_id']);
        $party = ExternalParty::from($validated['external_party']);
        CounterpartyPartyResolver::assertPartyMatchesContractorType($contractor, $party);

        $contextOrder = isset($validated['order_id'])
            ? Order::query()->find($validated['order_id'])
            : null;

        $conversation = $this->counterpartyConversationService->findOrCreateThread(
            $user,
            $contractor,
            $party,
            $contextOrder,
        );

        $conversation->loadMissing(['latestMessage.author:id,name', 'participants', 'contractor:id,name']);

        return response()->json([
            'conversation' => $this->serializeConversation($conversation, $user),
        ]);
    }

    public function counterpartyOrders(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);
        $this->authorizeParticipant($user, $conversation);
        abort_unless($this->counterpartyConversationService->isCounterpartyChannel($conversation), 404);

        return response()->json([
            'orders' => $this->counterpartyConversationService->ordersForConversation($conversation),
        ]);
    }

    public function openDirect(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $other = User::query()->findOrFail($validated['user_id']);
        $this->authorizeDirectRecipient($user, $other);

        $conversation = $this->messengerService->findOrCreateDirect($user, $other);
        $conversation->loadMissing(['latestMessage.author:id,name', 'participants']);

        return response()->json([
            'conversation' => $this->serializeConversation($conversation, $user),
        ]);
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'user_ids' => ['required', 'array', 'min:1', 'max:50'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id', Rule::notIn([$user->id])],
        ]);

        try {
            $conversation = $this->messengerService->createGroup(
                $user,
                $validated['title'],
                $validated['user_ids']
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $conversation->loadMissing(['latestMessage.author:id,name', 'participants']);

        return response()->json([
            'conversation' => $this->serializeConversation($conversation, $user),
        ]);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);
        $this->authorizeParticipant($user, $conversation);

        $afterId = $request->integer('after_id');
        $markRead = ! $request->boolean('skip_read');

        $query = ChatMessage::query()
            ->where('conversation_id', $conversation->id)
            ->with(['author:id,name', 'recipient:id,name']);

        if ($afterId > 0) {
            $messages = $query
                ->where('id', '>', $afterId)
                ->orderBy('id')
                ->limit(100)
                ->get()
                ->values();
        } else {
            $messages = $query
                ->orderByDesc('id')
                ->limit(100)
                ->get()
                ->reverse()
                ->values();
        }

        if ($markRead) {
            $this->messengerService->markRead($conversation, $user);
        }

        return response()->json([
            'messages' => $messages->map(fn (ChatMessage $m): array => $this->serializeMessage($m)),
        ]);
    }

    public function storeMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);
        $this->authorizeParticipant($user, $conversation);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:8000'],
            'recipient_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ]);

        $recipientId = $validated['recipient_user_id'] ?? null;
        if ($conversation->type !== 'group') {
            $recipientId = null;
        } elseif ($recipientId !== null) {
            if (! $conversation->participants()->where('user_id', $recipientId)->exists()) {
                throw ValidationException::withMessages([
                    'recipient_user_id' => ['Указанный получатель не состоит в этой группе.'],
                ]);
            }
        }

        $payload = [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'body' => $validated['body'],
        ];
        if (Schema::hasColumn('chat_messages', 'recipient_user_id')) {
            $payload['recipient_user_id'] = $recipientId;
        }

        if (
            Schema::hasColumn('chat_messages', 'order_id')
            && filled($validated['order_id'] ?? null)
        ) {
            $this->authorizeMessageOrder($user, $conversation, (int) $validated['order_id']);
            $payload['order_id'] = (int) $validated['order_id'];
        }

        if (Schema::hasColumn('chat_messages', 'message_type')) {
            $payload['message_type'] = 'text';
        }

        $message = ChatMessage::query()->create($payload);

        $conversation->touch();

        $message->load(['author:id,name', 'recipient:id,name']);
        $this->cabinetNotifier->notifyChatMessage($message, $user);

        return response()->json([
            'message' => $this->serializeMessage($message),
        ]);
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);
        $this->authorizeParticipant($user, $conversation);

        $this->messengerService->markRead($conversation, $user);

        return response()->json(['ok' => true]);
    }

    private function authorizeParticipant(User $user, Conversation $conversation): void
    {
        abort_unless(
            $conversation->participants()->where('user_id', $user->id)->exists(),
            403
        );
    }

    private function authorizeDirectRecipient(User $user, User $other): void
    {
        if (! $user->isExternal()) {
            return;
        }

        abort_unless(! $other->isExternal(), 403);

        $allowedStaffIds = collect($this->staffContactsForExternalUser($user))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        abort_unless(in_array((int) $other->id, $allowedStaffIds, true), 403);
    }

    private function authorizeMessageOrder(User $user, Conversation $conversation, int $orderId): void
    {
        $isCounterpartyChannel = $this->counterpartyConversationService->isCounterpartyChannel($conversation);

        if (! $user->isExternal() && ! $isCounterpartyChannel) {
            return;
        }

        abort_unless($isCounterpartyChannel, 403);

        $order = Order::query()->find($orderId);
        abort_if($order === null, 404);

        $externalUser = $this->counterpartyConversationService->externalParticipant($conversation);
        abort_unless($externalUser instanceof User, 403);
        abort_unless($this->counterpartyOrderAccess->userCanViewOrder($externalUser, $order), 403);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function staffContactsForExternalUser(User $externalUser): array
    {
        $hasPhoneColumn = Schema::hasColumn('users', 'phone');

        $staffIds = $externalUser->conversations()
            ->with('participants:id')
            ->get()
            ->flatMap(fn (Conversation $conversation) => $conversation->participants->pluck('id'))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->filter(fn (int $id): bool => $id !== (int) $externalUser->id)
            ->values()
            ->all();

        if ($staffIds === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $staffIds)
            ->when(Schema::hasColumn('users', 'is_active'), fn ($q) => $q->where('is_active', true))
            ->when(
                Schema::hasColumn('users', 'is_external'),
                fn ($q) => $q->where(function ($query): void {
                    $query->where('is_external', false)->orWhereNull('is_external');
                }),
            )
            ->orderBy('name')
            ->get(['id', 'name', 'email', $hasPhoneColumn ? 'phone' : 'id'])
            ->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $hasPhoneColumn ? $u->phone : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeConversation(Conversation $conversation, User $viewer): array
    {
        $conversation->loadMissing(['latestMessage.author:id,name', 'participants', 'contractor:id,name']);

        $channel = Schema::hasColumn('conversations', 'channel')
            ? (string) ($conversation->channel ?? 'internal')
            : 'internal';

        $other = $conversation->otherParticipant($viewer);
        if ($channel === 'counterparty' && $other === null) {
            $other = $conversation->participants
                ->first(fn (User $participant): bool => (int) $participant->id !== (int) $viewer->id);
        }

        $unread = $this->messengerService->unreadCountFor($conversation, $viewer);
        $latest = $conversation->latestMessage;
        $memberCount = $conversation->participants->count();
        $hasPhoneColumn = Schema::hasColumn('users', 'phone');
        $membersPreview = $conversation->type === 'group'
            ? $conversation->participants->sortBy('name')->take(4)->pluck('name')->values()->all()
            : [];
        $groupMembers = $conversation->type === 'group'
            ? $conversation->participants->sortBy('name')->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
            ])->values()->all()
            : [];

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'channel' => $channel,
            'contractor_id' => $conversation->contractor_id,
            'contractor_name' => $conversation->contractor?->name,
            'external_party' => $conversation->external_party,
            'title' => $conversation->type === 'group' ? $conversation->title : null,
            'member_count' => $memberCount,
            'members_preview' => $membersPreview,
            'group_members' => $groupMembers,
            'other_user' => $other === null ? null : [
                'id' => $other->id,
                'name' => $other->name,
                'phone' => $hasPhoneColumn ? $other->phone : null,
                'is_external' => Schema::hasColumn('users', 'is_external') ? $other->isExternal() : false,
            ],
            'last_message' => $latest === null ? null : [
                'user_id' => $latest->user_id,
                'body' => Str::limit((string) $latest->body, 120),
                'created_at' => $latest->created_at?->toIso8601String(),
                'author_name' => $latest->author?->name,
                'message_type' => Schema::hasColumn('chat_messages', 'message_type') ? $latest->message_type : 'text',
            ],
            'unread_count' => $unread,
            'updated_at' => $conversation->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMessage(ChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'author_name' => $message->author?->name,
            'recipient_user_id' => $message->recipient_user_id,
            'recipient_name' => $message->recipient?->name,
            'body' => $message->body,
            'order_id' => Schema::hasColumn('chat_messages', 'order_id') ? $message->order_id : null,
            'message_type' => Schema::hasColumn('chat_messages', 'message_type') ? ($message->message_type ?? 'text') : 'text',
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
