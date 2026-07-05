<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Services\CabinetNotifier;
use App\Services\MessengerService;
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

    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        if (! Schema::hasTable('conversations')) {
            return response()->json(['conversations' => [], 'unread_count' => 0]);
        }

        $items = $user->conversations()
            ->with(['latestMessage.author:id,name'])
            ->orderByDesc('conversations.updated_at')
            ->get()
            ->map(fn (Conversation $c): array => $this->serializeConversation($c, $user));

        return response()->json([
            'conversations' => $items,
            'unread_count' => $this->messengerService->totalUnreadFor($user),
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

    /**
     * @return array<string, mixed>
     */
    private function serializeConversation(Conversation $conversation, User $viewer): array
    {
        $conversation->loadMissing(['latestMessage.author:id,name', 'participants']);

        $other = $conversation->otherParticipant($viewer);
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
            'title' => $conversation->type === 'group' ? $conversation->title : null,
            'member_count' => $memberCount,
            'members_preview' => $membersPreview,
            'group_members' => $groupMembers,
            'other_user' => $other === null ? null : [
                'id' => $other->id,
                'name' => $other->name,
                'phone' => $hasPhoneColumn ? $other->phone : null,
            ],
            'last_message' => $latest === null ? null : [
                'user_id' => $latest->user_id,
                'body' => Str::limit((string) $latest->body, 120),
                'created_at' => $latest->created_at?->toIso8601String(),
                'author_name' => $latest->author?->name,
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
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
