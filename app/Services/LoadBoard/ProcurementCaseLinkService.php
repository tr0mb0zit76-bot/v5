<?php

namespace App\Services\LoadBoard;

use App\Models\Lead;
use App\Models\LoadBoardPost;
use App\Models\Order;
use App\Models\ProcurementCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProcurementCaseLinkService
{
    /**
     * @return array{linked_orders: list<array{id: int, order_number: string|null}>, linked_leads: list<array{id: int, number: string|null, title: string|null}>}
     */
    public function bootstrapLinksMetadata(LoadBoardPost $post): array
    {
        $linkedOrders = [];
        $linkedLeads = [];

        if ($post->order_id !== null) {
            $order = $post->relationLoaded('order')
                ? $post->order
                : Order::query()->select(['id', 'order_number'])->find($post->order_id);

            if ($order instanceof Order) {
                $linkedOrders[] = [
                    'id' => (int) $order->id,
                    'order_number' => $order->order_number,
                ];
            }
        }

        if ($post->lead_id !== null) {
            $lead = $post->relationLoaded('lead')
                ? $post->lead
                : Lead::query()->select(['id', 'number', 'title'])->find($post->lead_id);

            if ($lead instanceof Lead) {
                $linkedLeads[] = [
                    'id' => (int) $lead->id,
                    'number' => $lead->number,
                    'title' => $lead->title,
                ];
            }
        }

        return [
            'linked_orders' => $linkedOrders,
            'linked_leads' => $linkedLeads,
        ];
    }

    public function attachOrder(ProcurementCase $case, int $orderId): ProcurementCase
    {
        if (! Schema::hasTable('orders')) {
            throw ValidationException::withMessages(['id' => 'Заказы недоступны.']);
        }

        $order = Order::query()->select(['id', 'order_number'])->find($orderId);
        if ($order === null) {
            throw ValidationException::withMessages(['id' => 'Заказ не найден.']);
        }

        $metadata = is_array($case->metadata) ? $case->metadata : [];
        $linkedOrders = is_array($metadata['linked_orders'] ?? null) ? $metadata['linked_orders'] : [];

        if (! $this->linkedOrdersContain($linkedOrders, $orderId)) {
            $linkedOrders[] = [
                'id' => (int) $order->id,
                'order_number' => $order->order_number,
            ];
        }

        $metadata['linked_orders'] = array_values($linkedOrders);

        if ($case->order_id === null) {
            $case->order_id = $order->id;
        }

        $case->forceFill(['metadata' => $metadata])->save();

        return $case->fresh();
    }

    public function attachLead(ProcurementCase $case, int $leadId): ProcurementCase
    {
        if (! Schema::hasTable('leads')) {
            throw ValidationException::withMessages(['id' => 'Лиды недоступны.']);
        }

        $lead = Lead::query()->select(['id', 'number', 'title'])->find($leadId);
        if ($lead === null) {
            throw ValidationException::withMessages(['id' => 'Лид не найден.']);
        }

        $metadata = is_array($case->metadata) ? $case->metadata : [];
        $linkedLeads = is_array($metadata['linked_leads'] ?? null) ? $metadata['linked_leads'] : [];

        if (! $this->linkedLeadsContain($linkedLeads, $leadId)) {
            $linkedLeads[] = [
                'id' => (int) $lead->id,
                'number' => $lead->number,
                'title' => $lead->title,
            ];
        }

        $metadata['linked_leads'] = array_values($linkedLeads);

        if ($case->lead_id === null) {
            $case->lead_id = $lead->id;
        }

        $case->forceFill(['metadata' => $metadata])->save();

        return $case->fresh();
    }

    /**
     * @param  list<array<string, mixed>>  $linkedOrders
     */
    private function linkedOrdersContain(array $linkedOrders, int $orderId): bool
    {
        foreach ($linkedOrders as $row) {
            if ((int) ($row['id'] ?? 0) === $orderId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $linkedLeads
     */
    private function linkedLeadsContain(array $linkedLeads, int $leadId): bool
    {
        foreach ($linkedLeads as $row) {
            if ((int) ($row['id'] ?? 0) === $leadId) {
                return true;
            }
        }

        return false;
    }
}
