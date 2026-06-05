<?php

namespace App\Services\Commercial;

use App\Models\Contractor;
use App\Models\MailThread;
use App\Models\Order;
use App\Models\User;
use App\Services\CommercialMailService;
use App\Support\MailSync\MailMessageBodyPresenter;
use App\Support\RoleAccess;

final class OrderMailContextService
{
    public function __construct(
        private readonly CommercialMailService $commercialMail,
        private readonly MailMailboxAuthorization $mailboxAuth,
    ) {}

    public function userCanAccessMail(?User $user): bool
    {
        return $user instanceof User && RoleAccess::canAccessVisibilityArea($user, 'mail');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function threadSummariesForOrder(User $user, Order $order, int $limit = 30): array
    {
        if (! $this->userCanAccessMail($user) || ! $this->commercialMail->tablesReady()) {
            return [];
        }

        $limit = max(1, min($limit, 50));

        $query = MailThread::query()
            ->with([
                'messages' => fn ($q) => $q->orderByDesc('sent_at')->orderByDesc('id')->limit(1),
                'lead:id,number,title',
                'contractor:id,name',
                'order:id,order_number',
            ])
            ->where('order_id', $order->id)
            ->orderByDesc('last_message_at')
            ->limit($limit);

        $this->mailboxAuth->applyThreadScope($query, $user);

        return $query
            ->get()
            ->map(fn (MailThread $thread): array => $this->serializeThread($thread))
            ->values()
            ->all();
    }

    /**
     * @return array{order_id: int, order_number: string|null, to: list<string>, subject: string}|null
     */
    public function composeDefaultsForOrder(Order $order): ?array
    {
        $emails = $this->resolveCustomerEmails($order);

        if ($emails === []) {
            return null;
        }

        $orderNumber = trim((string) ($order->order_number ?? ''));

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'to' => $emails,
            'subject' => $orderNumber !== '' ? 'Заказ '.$orderNumber : 'По заказу #'.$order->id,
        ];
    }

    /**
     * @return array{order_id: int, order_number: string|null, to: list<string>, subject: string}|null
     */
    public function composeDefaultsForOrderId(int $orderId): ?array
    {
        $order = Order::query()->find($orderId);

        return $order instanceof Order ? $this->composeDefaultsForOrder($order) : null;
    }

    /**
     * @return list<string>
     */
    private function resolveCustomerEmails(Order $order): array
    {
        if ($order->customer_id === null) {
            return [];
        }

        $contractor = Contractor::query()->find($order->customer_id);

        if ($contractor === null) {
            return [];
        }

        $emails = [];

        foreach ([$contractor->contact_person_email, $contractor->email] as $candidate) {
            $normalized = strtolower(trim((string) $candidate));

            if ($normalized !== '' && filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
                $emails[$normalized] = $normalized;
            }
        }

        return array_values($emails);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeThread(MailThread $thread): array
    {
        $latest = $thread->relationLoaded('messages') ? $thread->messages->first() : null;

        return [
            'id' => $thread->id,
            'subject' => $thread->subject,
            'lead_id' => $thread->lead_id,
            'lead_number' => $thread->lead?->number,
            'lead_title' => $thread->lead?->title,
            'order_id' => $thread->order_id,
            'order_number' => $thread->order?->order_number,
            'contractor_id' => $thread->contractor_id,
            'contractor_name' => $thread->contractor?->name,
            'last_message_at' => $thread->last_message_at?->toIso8601String(),
            'last_inbound_at' => $thread->last_inbound_at?->toIso8601String(),
            'last_outbound_at' => $thread->last_outbound_at?->toIso8601String(),
            'preview' => $latest !== null
                ? MailMessageBodyPresenter::preview($latest)
                : null,
        ];
    }
}
