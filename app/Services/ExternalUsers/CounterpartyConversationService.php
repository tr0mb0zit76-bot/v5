<?php

namespace App\Services\ExternalUsers;

use App\Models\Contractor;
use App\Models\ContractorContact;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\User;
use App\Support\CounterpartyOrderAccess;
use App\Support\ExternalParty;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CounterpartyConversationService
{
    public function __construct(
        private readonly CounterpartyOrderAccess $orderAccess,
    ) {}

    public function findOrCreateThread(
        User $staffUser,
        Contractor $contractor,
        ExternalParty $party,
        ?Order $contextOrder = null,
    ): Conversation {
        $this->assertStaffUser($staffUser);

        $externalUser = $this->resolveExternalUser($contractor, $party);

        if ($contextOrder !== null) {
            abort_unless($this->orderAccess->userCanViewOrder($externalUser, $contextOrder), 403);
        }

        $conversation = $this->findExistingThread($staffUser, $contractor, $party);

        if ($conversation === null) {
            $conversation = DB::transaction(function () use ($staffUser, $contractor, $party, $externalUser): Conversation {
                $conversation = Conversation::query()->create([
                    'type' => 'direct',
                    'channel' => 'counterparty',
                    'contractor_id' => $contractor->id,
                    'external_party' => $party->value,
                    'primary_staff_user_id' => $staffUser->id,
                    'created_by' => $staffUser->id,
                ]);

                $conversation->participants()->attach([
                    $staffUser->id => [],
                    $externalUser->id => [],
                ]);

                return $conversation;
            });
        }

        if ($contextOrder !== null) {
            $this->maybePostOrderContextMessage($conversation, $staffUser, $contextOrder);
        }

        return $conversation->fresh(['participants', 'latestMessage.author']);
    }

    public function findOrCreateThreadForExternalUser(User $externalUser, User $staffUser): Conversation
    {
        $this->assertExternalUser($externalUser);

        $contractor = $externalUser->contractor;
        $party = $externalUser->externalParty();

        if ($contractor === null || $party === null) {
            throw ValidationException::withMessages([
                'user' => 'Профиль внешнего пользователя настроен не полностью.',
            ]);
        }

        return $this->findOrCreateThread($staffUser, $contractor, $party);
    }

    /**
     * @param  list<int>  $participantIds
     */
    public function assertGroupParticipantsAllowed(array $participantIds): void
    {
        if (! Schema::hasColumn('users', 'is_external')) {
            return;
        }

        $externals = User::query()
            ->whereIn('id', $participantIds)
            ->where('is_external', true)
            ->get();

        if ($externals->count() > 1) {
            throw ValidationException::withMessages([
                'user_ids' => 'В группе может быть только один внешний контакт.',
            ]);
        }

        $parties = $externals
            ->pluck('external_party')
            ->filter(fn (?string $party): bool => filled($party))
            ->unique()
            ->values();

        if ($parties->count() > 1) {
            throw ValidationException::withMessages([
                'user_ids' => 'Нельзя объединять заказчика и перевозчика в одном чате.',
            ]);
        }
    }

    public function assertDirectPairAllowed(User $a, User $b): void
    {
        if (! Schema::hasColumn('users', 'is_external')) {
            return;
        }

        if ($a->isExternal() && $b->isExternal()) {
            throw ValidationException::withMessages([
                'user_id' => 'Внешние контакты не могут переписываться между собой.',
            ]);
        }

        if ($a->isExternal() xor $b->isExternal()) {
            $external = $a->isExternal() ? $a : $b;
            $staff = $a->isExternal() ? $b : $a;

            if ($staff->isExternal()) {
                throw ValidationException::withMessages([
                    'user_id' => 'Недопустимая пара участников.',
                ]);
            }

            if ((int) $external->contractor_id <= 0 || $external->externalParty() === null) {
                throw ValidationException::withMessages([
                    'user_id' => 'Профиль внешнего контакта не настроен.',
                ]);
            }
        }
    }

    /**
     * @return list<array{id: int, order_number: string|null, route_summary: string|null, status: string|null}>
     */
    public function ordersForConversation(Conversation $conversation): array
    {
        if (! $this->isCounterpartyChannel($conversation)) {
            return [];
        }

        $externalUser = $this->externalParticipant($conversation);

        if ($externalUser === null) {
            return [];
        }

        return $this->orderAccess
            ->ordersQueryForUser($externalUser)
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get(['id', 'order_number', 'status', 'loading_city', 'loading_address', 'unloading_city', 'unloading_address'])
            ->map(fn (Order $order): array => [
                'id' => (int) $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'route_summary' => $this->routeSummary($order),
            ])
            ->values()
            ->all();
    }

    public function isCounterpartyChannel(Conversation $conversation): bool
    {
        return Schema::hasColumn('conversations', 'channel')
            && ($conversation->channel ?? 'internal') === 'counterparty';
    }

    public function externalParticipant(Conversation $conversation): ?User
    {
        $conversation->loadMissing('participants');

        return $conversation->participants->first(fn (User $user): bool => $user->isExternal());
    }

    private function findExistingThread(User $staffUser, Contractor $contractor, ExternalParty $party): ?Conversation
    {
        if (! Schema::hasColumn('conversations', 'channel')) {
            return null;
        }

        return Conversation::query()
            ->where('channel', 'counterparty')
            ->where('contractor_id', $contractor->id)
            ->where('external_party', $party->value)
            ->where('primary_staff_user_id', $staffUser->id)
            ->whereHas('participants', fn ($query) => $query->where('user_id', $staffUser->id))
            ->first();
    }

    private function resolveExternalUser(Contractor $contractor, ExternalParty $party): User
    {
        $query = User::query()
            ->where('is_external', true)
            ->where('is_active', true)
            ->where('contractor_id', $contractor->id)
            ->where('external_party', $party->value);

        if (Schema::hasColumn('contractor_contacts', 'is_traklo_primary')) {
            $primaryContactId = ContractorContact::query()
                ->where('contractor_id', $contractor->id)
                ->where('is_traklo_primary', true)
                ->value('id');

            if ($primaryContactId !== null) {
                $primaryUser = (clone $query)
                    ->where('contractor_contact_id', $primaryContactId)
                    ->first();

                if ($primaryUser instanceof User) {
                    return $primaryUser;
                }
            }
        }

        $user = $query->orderBy('id')->first();

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'contractor_id' => 'Сначала пригласите контакт в Traklo.',
            ]);
        }

        return $user;
    }

    private function maybePostOrderContextMessage(Conversation $conversation, User $author, Order $order): void
    {
        if (! Schema::hasColumn('chat_messages', 'order_id')) {
            return;
        }

        $number = $order->order_number ?: '#'.$order->id;
        $body = 'Обсуждаем заказ '.$number;

        $alreadyExists = $conversation->messages()
            ->where('order_id', $order->id)
            ->where('message_type', 'system')
            ->exists();

        if ($alreadyExists) {
            return;
        }

        $conversation->messages()->create([
            'user_id' => $author->id,
            'body' => $body,
            'order_id' => $order->id,
            'message_type' => 'system',
        ]);

        $conversation->touch();
    }

    private function assertStaffUser(User $user): void
    {
        if ($user->isExternal()) {
            throw ValidationException::withMessages([
                'user' => 'Только сотрудник может открыть чат с контрагентом.',
            ]);
        }
    }

    private function assertExternalUser(User $user): void
    {
        if (! $user->isExternal()) {
            throw ValidationException::withMessages([
                'user' => 'Ожидается внешний пользователь.',
            ]);
        }
    }

    private function routeSummary(Order $order): ?string
    {
        $loading = trim((string) ($order->loading_city ?? $order->loading_address ?? ''));
        $unloading = trim((string) ($order->unloading_city ?? $order->unloading_address ?? ''));

        if ($loading === '' && $unloading === '') {
            return null;
        }

        if ($loading === '' || $unloading === '') {
            return $loading !== '' ? $loading : $unloading;
        }

        return $loading.' → '.$unloading;
    }
}
