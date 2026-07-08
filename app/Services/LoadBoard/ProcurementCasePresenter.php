<?php

namespace App\Services\LoadBoard;

use App\Models\ProcurementCase;

class ProcurementCasePresenter
{
    /**
     * @return array<string, mixed>|null
     */
    public function present(?ProcurementCase $case): ?array
    {
        if ($case === null) {
            return null;
        }

        $metadata = is_array($case->metadata) ? $case->metadata : [];

        return [
            'id' => $case->id,
            'status' => $case->status,
            'order_id' => $case->order_id,
            'lead_id' => $case->lead_id,
            'order_owner' => $case->orderOwner?->only(['id', 'name']),
            'buyer' => $case->buyer?->only(['id', 'name']),
            'dispatcher' => $case->dispatcher?->only(['id', 'name']),
            'order' => $case->order?->only(['id', 'order_number']),
            'lead' => $case->lead?->only(['id', 'number', 'title']),
            'buying_own_company' => $case->buyingOwnCompany?->only(['id', 'name']),
            'linked_orders' => array_values(is_array($metadata['linked_orders'] ?? null) ? $metadata['linked_orders'] : []),
            'linked_leads' => array_values(is_array($metadata['linked_leads'] ?? null) ? $metadata['linked_leads'] : []),
        ];
    }
}
