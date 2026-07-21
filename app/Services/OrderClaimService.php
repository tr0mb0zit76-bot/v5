<?php

namespace App\Services;

use App\Enums\OrderClaimParty;
use App\Enums\OrderClaimStatus;
use App\Enums\OrderClaimType;
use App\Models\Order;
use App\Models\OrderClaim;
use App\Models\User;
use App\Support\ActivityEventType;
use App\Support\CrmFeatureCatalog;
use App\Support\OrderViewAuthorization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderClaimService
{
    public function __construct(
        private readonly ActivityLedgerService $activityLedger,
    ) {}

    public function tablesReady(): bool
    {
        return Schema::hasTable('order_claims');
    }

    public function featureEnabled(?User $user = null): bool
    {
        return CrmFeatureCatalog::isEnabled('order_claims', $user);
    }

    public function userCanAccess(?User $user, Order $order): bool
    {
        return $user !== null
            && $this->featureEnabled($user)
            && $this->tablesReady()
            && OrderViewAuthorization::userCanViewOrder($user, $order);
    }

    public function userCanMutate(?User $user, Order $order): bool
    {
        return $user !== null
            && $this->featureEnabled($user)
            && $this->tablesReady()
            && OrderViewAuthorization::userCanMutateOrder($user, $order);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function partyOptions(): array
    {
        return OrderClaimParty::options();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function typeOptions(): array
    {
        return OrderClaimType::options();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function statusOptions(): array
    {
        return OrderClaimStatus::options();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listForOrder(Order $order): Collection
    {
        if (! $this->tablesReady()) {
            return collect();
        }

        return OrderClaim::query()
            ->where('order_id', $order->id)
            ->with(['responsible:id,name', 'creator:id,name', 'contractor:id,name'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (OrderClaim $claim): array => $this->serialize($claim));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function listForUser(User $user, array $filters = [], int $limit = 100): Collection
    {
        if (! $this->tablesReady() || ! $this->featureEnabled($user)) {
            return collect();
        }

        $query = OrderClaim::query()
            ->with([
                'order:id,order_number,manager_id',
                'responsible:id,name',
                'creator:id,name',
                'contractor:id,name',
            ])
            ->orderByDesc('id')
            ->limit(max(1, min(200, $limit)));

        $this->applyOrderVisibility($query, $user);

        if (! empty($filters['status']) && is_string($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['q']) && is_string($filters['q'])) {
            $q = trim($filters['q']);
            if ($q !== '') {
                $query->where(function (Builder $builder) use ($q): void {
                    $builder
                        ->where('number', 'like', '%'.$q.'%')
                        ->orWhere('title', 'like', '%'.$q.'%')
                        ->orWhereHas('order', fn (Builder $orderQuery) => $orderQuery->where('order_number', 'like', '%'.$q.'%'));
                });
            }
        }

        return $query->get()->map(fn (OrderClaim $claim): array => $this->serialize($claim, true));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Order $order, User $user, array $data): OrderClaim
    {
        abort_unless($this->userCanMutate($user, $order), 403);

        return DB::transaction(function () use ($order, $user, $data): OrderClaim {
            $status = OrderClaimStatus::from((string) ($data['status'] ?? OrderClaimStatus::Open->value));

            $claim = OrderClaim::query()->create([
                'order_id' => $order->id,
                'contractor_id' => $data['contractor_id'] ?? null,
                'number' => $this->nextNumber(),
                'party' => OrderClaimParty::from((string) $data['party']),
                'type' => OrderClaimType::from((string) $data['type']),
                'status' => $status,
                'title' => (string) $data['title'],
                'description' => $data['description'] ?? null,
                'amount_risk' => $data['amount_risk'] ?? null,
                'currency' => strtoupper((string) ($data['currency'] ?? 'RUB')),
                'responsible_id' => $data['responsible_id'] ?? $user->id,
                'created_by' => $user->id,
                'due_at' => $data['due_at'] ?? null,
                'resolved_at' => $status->isTerminal() ? now() : null,
                'resolution_note' => $data['resolution_note'] ?? null,
            ]);

            $this->activityLedger->record(
                $order,
                ActivityEventType::ClaimOpened,
                'Претензия '.$claim->number,
                $claim->title,
                [
                    'claim_id' => $claim->id,
                    'claim_number' => $claim->number,
                    'status' => $claim->status->value,
                    'type' => $claim->type->value,
                    'party' => $claim->party->value,
                ],
                user: $user,
                source: $claim,
            );

            return $claim->fresh(['responsible', 'creator', 'contractor']) ?? $claim;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(OrderClaim $claim, User $user, array $data): OrderClaim
    {
        $order = $claim->order ?? Order::query()->findOrFail($claim->order_id);
        abort_unless($this->userCanMutate($user, $order), 403);

        return DB::transaction(function () use ($claim, $user, $data, $order): OrderClaim {
            $previousStatus = $claim->status;

            if (array_key_exists('party', $data) && $data['party'] !== null) {
                $claim->party = OrderClaimParty::from((string) $data['party']);
            }
            if (array_key_exists('type', $data) && $data['type'] !== null) {
                $claim->type = OrderClaimType::from((string) $data['type']);
            }
            if (array_key_exists('status', $data) && $data['status'] !== null) {
                $claim->status = OrderClaimStatus::from((string) $data['status']);
            }
            if (array_key_exists('title', $data) && $data['title'] !== null) {
                $claim->title = (string) $data['title'];
            }
            if (array_key_exists('description', $data)) {
                $claim->description = $data['description'];
            }
            if (array_key_exists('amount_risk', $data)) {
                $claim->amount_risk = $data['amount_risk'];
            }
            if (array_key_exists('currency', $data) && $data['currency'] !== null) {
                $claim->currency = strtoupper((string) $data['currency']);
            }
            if (array_key_exists('contractor_id', $data)) {
                $claim->contractor_id = $data['contractor_id'];
            }
            if (array_key_exists('responsible_id', $data)) {
                $claim->responsible_id = $data['responsible_id'];
            }
            if (array_key_exists('due_at', $data)) {
                $claim->due_at = $data['due_at'];
            }
            if (array_key_exists('resolution_note', $data)) {
                $claim->resolution_note = $data['resolution_note'];
            }

            if ($claim->status->isTerminal()) {
                $claim->resolved_at = $claim->resolved_at ?? now();
            } else {
                $claim->resolved_at = null;
            }

            $claim->save();

            if ($previousStatus !== $claim->status) {
                $eventType = $claim->status->isTerminal()
                    ? ActivityEventType::ClaimClosed
                    : ActivityEventType::ClaimStatusChanged;

                $this->activityLedger->record(
                    $order,
                    $eventType,
                    'Претензия '.$claim->number,
                    sprintf('%s → %s', $previousStatus->label(), $claim->status->label()),
                    [
                        'claim_id' => $claim->id,
                        'claim_number' => $claim->number,
                        'from_status' => $previousStatus->value,
                        'to_status' => $claim->status->value,
                    ],
                    user: $user,
                    source: $claim,
                );
            }

            return $claim->fresh(['responsible', 'creator', 'contractor']) ?? $claim;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(OrderClaim $claim, bool $withOrder = false): array
    {
        $row = [
            'id' => $claim->id,
            'order_id' => $claim->order_id,
            'contractor_id' => $claim->contractor_id,
            'contractor_name' => $claim->contractor?->name,
            'number' => $claim->number,
            'party' => $claim->party->value,
            'party_label' => $claim->party->label(),
            'type' => $claim->type->value,
            'type_label' => $claim->type->label(),
            'status' => $claim->status->value,
            'status_label' => $claim->status->label(),
            'title' => $claim->title,
            'description' => $claim->description,
            'amount_risk' => $claim->amount_risk !== null ? (float) $claim->amount_risk : null,
            'currency' => $claim->currency,
            'responsible_id' => $claim->responsible_id,
            'responsible_name' => $claim->responsible?->name,
            'created_by' => $claim->created_by,
            'creator_name' => $claim->creator?->name,
            'due_at' => optional($claim->due_at)?->toIso8601String(),
            'resolved_at' => optional($claim->resolved_at)?->toIso8601String(),
            'resolution_note' => $claim->resolution_note,
            'created_at' => optional($claim->created_at)?->toIso8601String(),
            'updated_at' => optional($claim->updated_at)?->toIso8601String(),
            'edit_url' => $claim->order_id
                ? route('orders.edit', ['order' => $claim->order_id, 'tab' => 'claims'])
                : null,
        ];

        if ($withOrder) {
            $row['order_number'] = $claim->order?->order_number;
        }

        return $row;
    }

    private function nextNumber(): string
    {
        $prefix = 'CL-'.now()->format('ymd');
        $sequence = OrderClaim::query()
            ->where('number', 'like', $prefix.'-%')
            ->count() + 1;

        return sprintf('%s-%03d', $prefix, $sequence);
    }

    /**
     * @param  Builder<OrderClaim>  $query
     */
    private function applyOrderVisibility(Builder $query, User $user): void
    {
        $query->whereHas('order', function (Builder $orderQuery) use ($user): void {
            OrderViewAuthorization::applyOrdersVisibilityScope($orderQuery, $user, 'orders');
        });
    }
}
