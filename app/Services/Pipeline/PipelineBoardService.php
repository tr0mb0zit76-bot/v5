<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use App\Models\BusinessProcess;
use App\Models\Lead;
use App\Models\Order;
use App\Models\User;
use App\Services\Disposition\DispositionInProgressOrderScope;
use App\Support\EndToEndOrderPipelineColumn;
use App\Support\LeadViewAuthorization;
use App\Support\OrderTransportTypeResolver;
use App\Support\RoleAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class PipelineBoardService
{
    private const int ORDER_CARD_LIMIT = 250;

    private const int LEAD_CARD_LIMIT = 150;

    public function __construct(
        private readonly EndToEndPipelineSnapshot $snapshot,
        private readonly OrderTransportTypeResolver $transportTypes,
        private readonly DispositionInProgressOrderScope $dispositionScope,
        private readonly PipelineKpiService $kpi,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildOrdersBoard(User $user): array
    {
        $columns = [];

        foreach (EndToEndOrderPipelineColumn::boardOrder() as $column) {
            $columns[] = [
                'key' => $column->value,
                'label' => $column->label(),
                'cards' => [],
            ];
        }

        $columnIndex = [];

        foreach ($columns as $column) {
            $columnIndex[$column['key']] = $column;
        }

        $orders = $this->ordersForBoard($user)->take(self::ORDER_CARD_LIMIT);
        $overdueOrderIds = $this->kpi->overdueOrderIdsFor($orders->pluck('id')->all());

        foreach ($orders as $order) {
            $order->setAttribute('transport_type_label', $this->transportTypes->labelForOrder($order));
            $order->setAttribute('has_overdue_payments', in_array($order->id, $overdueOrderIds, true));
            $card = $this->snapshot->serializeOrderCard($order);
            $key = $card['pipeline_column'];

            if (isset($columnIndex[$key])) {
                $columnIndex[$key]['cards'][] = $card;
            }
        }

        return [
            'view' => 'orders',
            'columns' => array_values($columnIndex),
            'kpi' => $this->kpi->metricsForUser($user),
            'can_mark_accounting_handoff' => $this->canMarkAccountingHandoff($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildLeadsBoard(User $user, string $processSlug): array
    {
        $process = BusinessProcess::query()
            ->where('slug', $processSlug)
            ->where('is_active', true)
            ->with(['stages' => fn ($query) => $query->orderBy('sequence')])
            ->first();

        if ($process === null) {
            return [
                'view' => 'leads',
                'lead_process_slug' => $processSlug,
                'columns' => [],
                'processes' => $this->leadProcessOptions(),
                'error' => 'Бизнес-процесс не найден.',
                'kpi' => $this->kpi->metricsForUser($user),
            ];
        }

        $columns = [
            [
                'key' => 'unassigned',
                'label' => 'Без этапа',
                'stage_id' => null,
                'cards' => [],
            ],
        ];

        foreach ($process->stages as $stage) {
            $columns[] = [
                'key' => 'stage_'.$stage->id,
                'label' => $stage->name,
                'stage_id' => $stage->id,
                'cards' => [],
            ];
        }

        $columnIndex = [];

        foreach ($columns as $column) {
            $columnIndex[$column['key']] = $column;
        }

        foreach ($this->leadsForBoard($user, $process->id)->take(self::LEAD_CARD_LIMIT) as $lead) {
            $card = $this->snapshot->serializeLeadCard($lead);
            $key = $lead->business_process_stage_id
                ? 'stage_'.$lead->business_process_stage_id
                : 'unassigned';

            if (isset($columnIndex[$key])) {
                $columnIndex[$key]['cards'][] = $card;
            }
        }

        return [
            'view' => 'leads',
            'lead_process_slug' => $process->slug,
            'lead_process_name' => $process->name,
            'columns' => array_values($columnIndex),
            'processes' => $this->leadProcessOptions(),
            'kpi' => $this->kpi->metricsForUser($user),
            'can_advance_lead_stage' => RoleAccess::canAccessVisibilityArea($user, 'leads'),
        ];
    }

    /**
     * @return list<array{slug: string, name: string}>
     */
    public function leadProcessOptions(): array
    {
        if (! Schema::hasTable('business_processes')) {
            return [];
        }

        return BusinessProcess::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->map(fn (BusinessProcess $process): array => [
                'slug' => (string) $process->slug,
                'name' => (string) $process->name,
            ])
            ->all();
    }

    public function markAccountingHandoff(Order $order, User $user): void
    {
        abort_unless($this->canMarkAccountingHandoff($user), 403);
        abort_unless(Schema::hasColumn('orders', 'accounting_handoff_at'), 404);

        $order->forceFill([
            'accounting_handoff_at' => now(),
            'accounting_handoff_by' => $user->id,
        ])->save();
    }

    private function canMarkAccountingHandoff(User $user): bool
    {
        return RoleAccess::canAccessVisibilityArea($user, 'finance_salary')
            || RoleAccess::canAccessVisibilityArea($user, 'settings')
            || RoleAccess::isAdminUser($user);
    }

    /**
     * @return Builder<Order>
     */
    private function ordersQuery(User $user): Builder
    {
        $builder = Order::query()
            ->with([
                'client:id,name',
                'manager:id,name',
                'legs' => fn ($query) => $query->orderBy('sequence'),
                'legs.routePoints' => fn ($query) => $query->orderBy('sequence'),
            ])
            ->orderByDesc('id');

        $this->dispositionScope->applyVisibilityForArea($builder, $user, 'pipeline');

        return $builder;
    }

    /**
     * @return Collection<int, Order>
     */
    private function ordersForBoard(User $user): Collection
    {
        return $this->ordersQuery($user)->get();
    }

    /**
     * @return Builder<Lead>
     */
    private function leadsQuery(User $user): Builder
    {
        $builder = Lead::query()
            ->with([
                'counterparty:id,name',
                'responsible:id,name',
                'businessProcess:id,name,slug',
                'businessProcessStage:id,name,business_process_id',
            ])
            ->whereNotIn('status', ['won', 'lost'])
            ->orderByDesc('id');

        if (Schema::hasColumn('leads', 'deleted_at')) {
            $builder->whereNull('deleted_at');
        }

        if (! RoleAccess::isAdminUser($user)) {
            LeadViewAuthorization::applyLeadsVisibilityScope($builder, $user);
        }

        return $builder;
    }

    /**
     * @return Collection<int, Lead>
     */
    private function leadsForBoard(User $user, int $processId): Collection
    {
        return $this->leadsQuery($user)
            ->where(function (Builder $query) use ($processId): void {
                $query
                    ->where('business_process_id', $processId)
                    ->orWhereNull('business_process_id');
            })
            ->get()
            ->filter(function (Lead $lead) use ($processId): bool {
                if ($lead->business_process_id === null) {
                    return true;
                }

                return (int) $lead->business_process_id === $processId;
            })
            ->values();
    }
}
