<?php

namespace App\Services\Commercial;

use App\Models\Lead;
use App\Models\MailThread;
use App\Models\Order;

final class MailThreadLinkService
{
    /**
     * @return array{lead_id: int|null, order_id: int|null, contractor_id: int|null}
     */
    public function resolveLinkPayload(?int $leadId, ?int $orderId): array
    {
        $contractorId = null;

        if ($leadId !== null) {
            $lead = Lead::query()->find($leadId);
            $contractorId = $lead?->counterparty_id;
        }

        if ($contractorId === null && $orderId !== null) {
            $order = Order::query()->find($orderId);
            $contractorId = $order?->customer_id;
        }

        return [
            'lead_id' => $leadId,
            'order_id' => $orderId,
            'contractor_id' => $contractorId !== null ? (int) $contractorId : null,
        ];
    }

    public function apply(MailThread $thread, ?int $leadId, ?int $orderId): MailThread
    {
        $payload = $this->resolveLinkPayload($leadId, $orderId);

        $thread->forceFill([
            'lead_id' => $payload['lead_id'],
            'order_id' => $payload['order_id'],
            'contractor_id' => $payload['contractor_id'] ?? $thread->contractor_id,
        ])->save();

        return $thread->refresh();
    }
}
