<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Support\RoleAccess;
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
            $conversationData = ['type' => 'group'];
            if (Schema::hasColumn('conversations', 'title')) {
                $conversationData['title'] = $title;
            }
            if (Schema::hasColumn('conversations', 'created_by')) {
                $conversationData['created_by'] = $creator->id;
            }

            $conversation = Conversation::query()->create($conversationData);

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

    /**
     * Документы заказов для чипов-ссылок (видимость согласована со списком заказов).
     * Без строки поиска — последние 40 по дате обновления; с непустым $search — фильтр по подстроке и числовому id (до 50 строк).
     *
     * @return list<array{id: int, order_id: int, label: string, url: string}>
     */
    public function orderDocumentsForChips(User $user, ?string $search = null): array
    {
        if (! Schema::hasTable('order_documents') || ! Schema::hasTable('orders')) {
            return [];
        }

        $user->loadMissing('role');

        if (! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'orders')) {
            return [];
        }

        $roleName = $user->role?->name;
        $scopes = $user->role?->visibility_scopes;
        if (is_string($scopes)) {
            $scopes = json_decode($scopes, true);
        }

        $ordersScope = RoleAccess::resolveVisibilityScope($roleName, is_array($scopes) ? $scopes : null, 'orders');

        $query = DB::table('order_documents')
            ->join('orders', 'orders.id', '=', 'order_documents.order_id')
            ->select([
                'order_documents.id',
                'order_documents.order_id',
                'order_documents.type',
                'order_documents.number',
                'order_documents.original_name',
            ]);

        if (Schema::hasColumn('orders', 'order_customer_number')) {
            $query->addSelect('orders.order_customer_number');
        }

        if ($roleName !== 'admin' && $ordersScope !== 'all') {
            $query->where('orders.manager_id', $user->id);
        }

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $query->whereNull('orders.deleted_at');
        }

        $needle = $search !== null ? trim($search) : '';

        if ($needle !== '') {
            $like = '%'.$this->escapeLikeForSearch($needle).'%';
            $query->where(function ($q) use ($like, $needle): void {
                $q->where('order_documents.type', 'like', $like)
                    ->orWhere('order_documents.number', 'like', $like)
                    ->orWhere('order_documents.original_name', 'like', $like);

                if (preg_match('/^\d+$/', $needle) === 1) {
                    $id = (int) $needle;
                    $q->orWhere('orders.id', $id)
                        ->orWhere('order_documents.order_id', $id)
                        ->orWhere('order_documents.id', $id);
                }

                if (Schema::hasColumn('orders', 'order_customer_number')) {
                    $q->orWhere('orders.order_customer_number', 'like', $like);
                }
            });
            $query->orderByDesc('order_documents.updated_at')->limit(50);
        } else {
            $query->orderByDesc('order_documents.updated_at')->limit(40);
        }

        $rows = $query->get();
        $out = [];

        foreach ($rows as $row) {
            $orderRef = $this->formatOrderRefForDocumentChip($row);
            $label = $this->formatDocumentChipLabel($row, $orderRef);
            $url = route('orders.edit', (int) $row->order_id, absolute: true).'?tab=documents';

            $out[] = [
                'id' => (int) $row->id,
                'order_id' => (int) $row->order_id,
                'label' => $label,
                'url' => $url,
            ];
        }

        return $out;
    }

    private function formatOrderRefForDocumentChip(object $row): string
    {
        if (property_exists($row, 'order_customer_number') && filled($row->order_customer_number)) {
            return (string) $row->order_customer_number;
        }

        return '#'.(int) $row->order_id;
    }

    /**
     * @param  object  $row  order_documents join row
     */
    private function formatDocumentChipLabel(object $row, string $orderRef): string
    {
        $type = trim((string) ($row->type ?? ''));
        $num = trim((string) ($row->number ?? ''));
        $parts = [];
        if ($type !== '') {
            $parts[] = $type;
        }
        if ($num !== '') {
            $parts[] = '№ '.$num;
        }
        if ($parts === []) {
            $fallback = trim((string) ($row->original_name ?? ''));
            $parts[] = $fallback !== '' ? $fallback : 'Документ';
        }
        $parts[] = 'Заказ '.$orderRef;

        return implode(' · ', $parts);
    }

    private function escapeLikeForSearch(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
