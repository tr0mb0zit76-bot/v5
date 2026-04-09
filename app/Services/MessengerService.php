<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MessengerService
{
    public function findOrCreateDirect(User $a, User $b): Conversation
    {
        if ($a->id === $b->id) {
            throw new \InvalidArgumentException('Нельзя открыть чат с самим собой.');
        }

        $existing = Conversation::query()
            ->where('type', 'direct')
            ->whereHas('participants', fn ($q) => $q->where('user_id', $a->id))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $b->id))
            ->withCount('participants')
            ->get()
            ->first(fn (Conversation $c): bool => $c->participants_count === 2);

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($a, $b): Conversation {
            $conversation = Conversation::query()->create(['type' => 'direct']);
            $conversation->participants()->attach([$a->id => [], $b->id => []]);

            return $conversation;
        });
    }

    public function createGroup(User $creator, string $title, array $userIds): Conversation
    {
        $ids = array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $userIds)));
        $ids = array_values(array_filter($ids, fn (int $id): bool => $id !== $creator->id));

        if ($ids === []) {
            throw new \InvalidArgumentException('Добавьте хотя бы одного участника.');
        }

        $query = User::query()->whereIn('id', $ids);
        if (Schema::hasColumn('users', 'is_active')) {
            $query->where('is_active', true);
        }

        $found = $query->count();
        if ($found !== count($ids)) {
            throw new \InvalidArgumentException('Некоторые пользователи недоступны.');
        }

        return DB::transaction(function () use ($creator, $title, $ids): Conversation {
            $conversation = Conversation::query()->create([
                'type' => 'group',
                'title' => $title,
                'created_by' => $creator->id,
            ]);

            $attach = [$creator->id => []];
            foreach ($ids as $userId) {
                $attach[$userId] = [];
            }
            $conversation->participants()->attach($attach);

            return $conversation->fresh();
        });
    }

    public function unreadCountFor(Conversation $conversation, User $user): int
    {
        $participant = $conversation->participants()->where('user_id', $user->id)->first();
        if ($participant === null) {
            return 0;
        }

        /** @var Carbon|null $lastRead */
        $lastRead = $participant->pivot->last_read_at;

        $query = ChatMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $user->id);

        if ($lastRead !== null) {
            $query->where('created_at', '>', $lastRead);
        }

        return $query->count();
    }

    public function totalUnreadFor(User $user): int
    {
        $total = 0;
        $conversations = $user->conversations()->get();

        foreach ($conversations as $conversation) {
            $total += $this->unreadCountFor($conversation, $user);
        }

        return $total;
    }

    public function markRead(Conversation $conversation, User $user): void
    {
        $conversation->participants()->updateExistingPivot($user->id, [
            'last_read_at' => now(),
        ]);
    }
}
