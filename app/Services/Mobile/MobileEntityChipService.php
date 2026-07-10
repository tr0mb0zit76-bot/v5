<?php

namespace App\Services\Mobile;

use App\Models\Contractor;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\User;
use App\Services\MessengerService;
use App\Support\LeadViewAuthorization;
use App\Support\OrderViewAuthorization;
use App\Support\RoleAccess;
use Illuminate\Support\Facades\Schema;

class MobileEntityChipService
{
    public function __construct(
        private MessengerService $messengerService,
    ) {}

    /**
     * @return array{entities: list<array<string, mixed>>}
     */
    public function search(User $user, ?string $search = null, ?string $kind = null): array
    {
        if ($user->isExternal()) {
            return ['entities' => []];
        }

        $needle = trim((string) $search);
        $entities = [];

        if ($kind === null || $kind === 'document') {
            $entities = array_merge($entities, $this->mapDocumentChips(
                $this->messengerService->orderDocumentsForChips($user, $needle !== '' ? $needle : null),
            ));
        }

        if ($kind === null || $kind === 'order') {
            $entities = array_merge($entities, $this->searchOrders($user, $needle));
        }

        if ($kind === null || $kind === 'lead') {
            $entities = array_merge($entities, $this->searchLeads($user, $needle));
        }

        if ($kind === null || $kind === 'contractor') {
            $entities = array_merge($entities, $this->searchContractors($user, $needle));
        }

        return ['entities' => $entities];
    }

    /**
     * @return array{kind: string, id: int, label: string, subtitle: string|null, url: string}
     */
    public function chipFromOrderDocument(OrderDocument $document, Order $order): array
    {
        $orderRef = filled($order->order_number)
            ? (string) $order->order_number
            : '#'.$order->id;

        $type = trim((string) $document->type);
        $number = trim((string) ($document->number ?? ''));
        $parts = [];

        if ($type !== '') {
            $parts[] = $type;
        }

        if ($number !== '') {
            $parts[] = '№ '.$number;
        }

        if ($parts === []) {
            $fallback = trim((string) ($document->original_name ?? ''));
            $parts[] = $fallback !== '' ? $fallback : 'Документ';
        }

        $parts[] = 'Заказ '.$orderRef;

        return [
            'kind' => 'document',
            'id' => (int) $document->id,
            'label' => implode(' · ', $parts),
            'subtitle' => $orderRef,
            'url' => route('orders.edit', $order, absolute: true).'?tab=documents',
        ];
    }

    /**
     * @param  list<array{id: int, order_id: int, label: string, url: string}>  $documents
     * @return list<array<string, mixed>>
     */
    private function mapDocumentChips(array $documents): array
    {
        return array_map(static fn (array $document): array => [
            'kind' => 'document',
            'id' => (int) $document['id'],
            'label' => (string) $document['label'],
            'subtitle' => 'Документ заказа #'.(int) $document['order_id'],
            'url' => (string) $document['url'],
        ], $documents);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchOrders(User $user, string $needle): array
    {
        if (! Schema::hasTable('orders') || ! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'orders')) {
            return [];
        }

        $query = Order::query()
            ->with(['client:id,name'])
            ->tap(fn ($builder) => OrderViewAuthorization::applyOrdersVisibilityScope($builder, $user, 'orders'));

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if ($needle !== '') {
            $like = '%'.$needle.'%';
            $query->where(function ($builder) use ($like, $needle): void {
                $builder->where('order_number', 'like', $like);

                if (Schema::hasColumn('orders', 'order_customer_number')) {
                    $builder->orWhere('order_customer_number', 'like', $like);
                }

                if (preg_match('/^\d+$/', $needle) === 1) {
                    $builder->orWhere('orders.id', (int) $needle);
                }
            });
            $limit = 20;
        } else {
            $query->orderByDesc('updated_at');
            $limit = 12;
        }

        return $query
            ->limit($limit)
            ->get()
            ->map(fn (Order $order): array => [
                'kind' => 'order',
                'id' => (int) $order->id,
                'label' => $order->order_number ?: '#'.$order->id,
                'subtitle' => $order->client?->name,
                'url' => route('orders.edit', $order, absolute: true),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchLeads(User $user, string $needle): array
    {
        if (! Schema::hasTable('leads') || ! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'leads')) {
            return [];
        }

        $query = Lead::query()
            ->with(['counterparty:id,name'])
            ->tap(fn ($builder) => LeadViewAuthorization::applyLeadsVisibilityScope($builder, $user));

        if ($needle !== '') {
            $like = '%'.$needle.'%';
            $query->where(function ($builder) use ($like, $needle): void {
                $builder->where('title', 'like', $like)
                    ->orWhere('number', 'like', $like);

                if (preg_match('/^\d+$/', $needle) === 1) {
                    $builder->orWhere('id', (int) $needle);
                }
            });
            $limit = 20;
        } else {
            $query->orderByDesc('updated_at');
            $limit = 12;
        }

        return $query
            ->limit($limit)
            ->get()
            ->map(fn (Lead $lead): array => [
                'kind' => 'lead',
                'id' => (int) $lead->id,
                'label' => trim(($lead->number ?: '#'.$lead->id).' · '.($lead->title ?: 'Лид')),
                'subtitle' => $lead->counterparty?->name,
                'url' => route('leads.show', $lead, absolute: true),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchContractors(User $user, string $needle): array
    {
        if (! Schema::hasTable('contractors') || ! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'contractors')) {
            return [];
        }

        $query = Contractor::query()
            ->visibleTo($user);

        if ($needle !== '') {
            $like = '%'.$needle.'%';
            $query->where(function ($builder) use ($like, $needle): void {
                $builder->where('name', 'like', $like)
                    ->orWhere('inn', 'like', $like);

                if (preg_match('/^\d+$/', $needle) === 1) {
                    $builder->orWhere('id', (int) $needle);
                }
            });
            $limit = 20;
        } else {
            $query->orderByDesc('updated_at');
            $limit = 12;
        }

        return $query
            ->limit($limit)
            ->get(['id', 'name', 'inn', 'type'])
            ->map(fn (Contractor $contractor): array => [
                'kind' => 'contractor',
                'id' => (int) $contractor->id,
                'label' => (string) $contractor->name,
                'subtitle' => filled($contractor->inn)
                    ? 'ИНН '.$contractor->inn
                    : (string) ($contractor->type ?? ''),
                'url' => route('contractors.show', $contractor, absolute: true),
            ])
            ->values()
            ->all();
    }
}
