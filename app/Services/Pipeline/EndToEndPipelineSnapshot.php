<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use App\Models\Lead;
use App\Models\Order;
use App\Services\OrderStatusService;
use App\Support\DispositionInTransitResolver;
use App\Support\EndToEndOrderPipelineColumn;
use Illuminate\Support\Facades\Schema;

final class EndToEndPipelineSnapshot
{
    public function __construct(
        private readonly OrderStatusService $orderStatus,
    ) {}

    public function orderColumn(Order $order): EndToEndOrderPipelineColumn
    {
        $effectiveStatus = $this->orderStatus->resolve(
            $order,
            $order->manual_status ?? $order->status,
        );

        if (in_array($effectiveStatus, ['cancelled', 'disruption'], true)) {
            return EndToEndOrderPipelineColumn::Disruption;
        }

        if ($effectiveStatus === 'closed') {
            if ($this->hasAccountingHandoff($order)) {
                return EndToEndOrderPipelineColumn::AccountingHandoff;
            }

            return EndToEndOrderPipelineColumn::Closed;
        }

        if ($effectiveStatus === 'payment') {
            return EndToEndOrderPipelineColumn::Payment;
        }

        if ($effectiveStatus === 'documents') {
            return EndToEndOrderPipelineColumn::Documents;
        }

        if ($effectiveStatus === 'in_progress' && DispositionInTransitResolver::isInTransit($order)) {
            return EndToEndOrderPipelineColumn::InTransit;
        }

        return EndToEndOrderPipelineColumn::Preparation;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeOrderCard(Order $order): array
    {
        $description = $this->orderStatus->describe(
            $order,
            $order->manual_status ?? $order->status,
        );

        $blockers = $description['messages'];

        if ((bool) $order->getAttribute('has_overdue_payments')) {
            $blockers[] = 'Просрочен график оплат';
        }

        return [
            'type' => 'order',
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $order->client?->name,
            'manager_name' => $order->manager?->name,
            'route_label' => $this->routeLabel($order),
            'transport_type_label' => $order->getAttribute('transport_type_label'),
            'status' => $description['status'],
            'status_label' => $description['label'],
            'pipeline_column' => $this->orderColumn($order)->value,
            'blockers' => $blockers,
            'lead_id' => $order->lead_id,
            'accounting_handoff_at' => $this->accountingHandoffAtIso($order),
            'edit_url' => route('orders.edit', $order, absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeLeadCard(Lead $lead): array
    {
        return [
            'type' => 'lead',
            'id' => $lead->id,
            'number' => $lead->number,
            'title' => $lead->title,
            'status' => $lead->status,
            'customer_name' => $lead->counterparty?->name,
            'responsible_name' => $lead->responsible?->name,
            'process_slug' => $lead->businessProcess?->slug,
            'process_name' => $lead->businessProcess?->name,
            'stage_id' => $lead->business_process_stage_id,
            'stage_name' => $lead->businessProcessStage?->name,
            'is_stage_overdue' => $lead->getAttribute('is_stage_overdue') ?? $this->isLeadStageOverdue($lead),
            'blockers' => $this->leadBlockers($lead),
            'edit_url' => route('leads.show', $lead, absolute: false),
        ];
    }

    public function isLeadActive(Lead $lead): bool
    {
        return ! in_array($lead->status, ['won', 'lost'], true);
    }

    private function hasAccountingHandoff(Order $order): bool
    {
        if (! Schema::hasColumn('orders', 'accounting_handoff_at')) {
            return false;
        }

        return $order->accounting_handoff_at !== null;
    }

    private function accountingHandoffAtIso(Order $order): ?string
    {
        if (! $this->hasAccountingHandoff($order)) {
            return null;
        }

        return $order->accounting_handoff_at?->toIso8601String();
    }

    private function routeLabel(Order $order): string
    {
        $loading = $order->loading_date?->format('d.m.Y');
        $unloading = $order->unloading_date?->format('d.m.Y');

        if ($loading === null && $unloading === null) {
            return '—';
        }

        return ($loading ?? '—').' → '.($unloading ?? '—');
    }

    /**
     * @return list<string>
     */
    private function leadBlockers(Lead $lead): array
    {
        $blockers = [];

        if ($this->isLeadStageOverdue($lead)) {
            $blockers[] = 'Просрочен этап бизнес-процесса';
        }

        if ($lead->business_process_id === null) {
            $blockers[] = 'Не выбран бизнес-процесс';
        }

        return $blockers;
    }

    private function isLeadStageOverdue(Lead $lead): bool
    {
        if ($lead->stage_due_at === null) {
            return false;
        }

        return $lead->stage_due_at->isPast();
    }
}
