<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderLink;
use App\Models\User;
use App\Support\OrderViewAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class OrderLinkService
{
    public function tablesReady(): bool
    {
        return Schema::hasTable('order_links');
    }

    /**
     * @return array{id: int, order_number: string|null, own_company_name: string|null, customer_name: string|null, edit_url: string}|null
     */
    public function linkedOrderPayload(Order $order): ?array
    {
        if (! $this->tablesReady()) {
            return null;
        }

        $peer = $this->peerOrder($order);
        if ($peer === null) {
            return null;
        }

        return $this->serializePeer($peer);
    }

    public function peerOrder(Order $order): ?Order
    {
        if (! $this->tablesReady()) {
            return null;
        }

        $link = OrderLink::query()
            ->where(function ($query) use ($order): void {
                $query->where('order_id', $order->id)
                    ->orWhere('linked_order_id', $order->id);
            })
            ->first();

        if ($link === null) {
            return null;
        }

        $peerId = (int) $link->order_id === (int) $order->id
            ? (int) $link->linked_order_id
            : (int) $link->order_id;

        return Order::query()
            ->with(['client:id,name', 'ownCompany:id,name'])
            ->find($peerId);
    }

    /**
     * @return list<array{id: int, order_number: string|null, label: string, own_company_name: string|null, customer_name: string|null}>
     */
    public function searchLinkCandidates(User $user, string $query, ?int $excludeOrderId = null, int $limit = 20): array
    {
        $q = trim($query);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $builder = Order::query()
            ->with(['client:id,name', 'ownCompany:id,name'])
            ->orderByDesc('id')
            ->limit($limit);

        if ($excludeOrderId !== null && $excludeOrderId > 0) {
            $builder->where('id', '!=', $excludeOrderId);
        }

        OrderViewAuthorization::applyOrdersVisibilityScope($builder, $user);

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
        $builder->where(function ($inner) use ($q, $like): void {
            $inner->where('order_number', 'like', $like);
            if (ctype_digit($q)) {
                $inner->orWhere('id', (int) $q);
            }
        });

        // Exclude orders that already have any expedition link.
        $builder->whereNotExists(function ($sub): void {
            $sub->select(DB::raw(1))
                ->from('order_links')
                ->where(function ($inner): void {
                    $inner->whereColumn('order_links.order_id', 'orders.id')
                        ->orWhereColumn('order_links.linked_order_id', 'orders.id');
                });
        });

        return $builder->get()->map(fn (Order $order): array => [
            'id' => (int) $order->id,
            'order_number' => $order->order_number,
            'label' => $this->labelFor($order),
            'own_company_name' => $order->ownCompany?->name,
            'customer_name' => $order->client?->name,
        ])->all();
    }

    public function link(Order $order, Order $peer, User $actor, string $linkType = OrderLink::TYPE_EXPEDITION_CHAIN): OrderLink
    {
        if (! $this->tablesReady()) {
            throw new InvalidArgumentException('Таблица связей заказов ещё не создана.');
        }

        if ((int) $order->id === (int) $peer->id) {
            throw new InvalidArgumentException('Нельзя связать заказ с самим собой.');
        }

        if ($this->peerOrder($order) !== null) {
            throw new InvalidArgumentException('У текущего заказа уже есть связанный заказ. Сначала отвяжите.');
        }

        if ($this->peerOrder($peer) !== null) {
            throw new InvalidArgumentException('Выбранный заказ уже связан с другим. Сначала отвяжите там.');
        }

        $left = min((int) $order->id, (int) $peer->id);
        $right = max((int) $order->id, (int) $peer->id);

        return OrderLink::query()->create([
            'order_id' => $left,
            'linked_order_id' => $right,
            'link_type' => $linkType !== '' ? $linkType : OrderLink::TYPE_EXPEDITION_CHAIN,
            'created_by' => $actor->id,
        ]);
    }

    public function unlink(Order $order): void
    {
        if (! $this->tablesReady()) {
            return;
        }

        OrderLink::query()
            ->where(function ($query) use ($order): void {
                $query->where('order_id', $order->id)
                    ->orWhere('linked_order_id', $order->id);
            })
            ->delete();
    }

    /**
     * @return array{id: int, order_number: string|null, own_company_name: string|null, customer_name: string|null, edit_url: string}
     */
    private function serializePeer(Order $peer): array
    {
        return [
            'id' => (int) $peer->id,
            'order_number' => $peer->order_number,
            'own_company_name' => $peer->ownCompany?->name,
            'customer_name' => $peer->client?->name,
            'edit_url' => route('orders.edit', $peer),
        ];
    }

    private function labelFor(Order $order): string
    {
        $number = filled($order->order_number) ? (string) $order->order_number : '#'.$order->id;
        $parts = array_filter([
            $number,
            $order->ownCompany?->name,
            $order->client?->name,
        ]);

        return implode(' · ', $parts);
    }
}
