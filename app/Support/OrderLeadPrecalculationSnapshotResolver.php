<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Facades\Schema;

final class OrderLeadPrecalculationSnapshotResolver
{
    /**
     * @return array<string, mixed>|null
     */
    public static function rawSnapshot(Order $order): ?array
    {
        if (Schema::hasColumn('orders', 'metadata') && is_array($order->metadata)) {
            $snapshot = $order->metadata['lead_precalculation_snapshot'] ?? null;

            if (is_array($snapshot) && $snapshot !== []) {
                return $snapshot;
            }
        }

        if (Schema::hasColumn('orders', 'wizard_state') && is_array($order->wizard_state)) {
            $snapshot = $order->wizard_state['lead_precalculation_snapshot'] ?? null;

            if (is_array($snapshot) && $snapshot !== []) {
                return $snapshot;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     precalculation: array<string, mixed>,
     *     snapshot_at: string|null,
     *     lead_id: int|null
     * }|null
     */
    public static function forWizard(Order $order): ?array
    {
        $raw = self::rawSnapshot($order);

        if ($raw === null) {
            return null;
        }

        $snapshotAt = null;

        if (Schema::hasColumn('orders', 'metadata') && is_array($order->metadata)) {
            $snapshotAt = filled($order->metadata['lead_precalculation_snapshot_at'] ?? null)
                ? (string) $order->metadata['lead_precalculation_snapshot_at']
                : null;
        }

        if ($snapshotAt === null && Schema::hasColumn('orders', 'wizard_state') && is_array($order->wizard_state)) {
            $snapshotAt = filled($order->wizard_state['lead_precalculation_snapshot_at'] ?? null)
                ? (string) $order->wizard_state['lead_precalculation_snapshot_at']
                : null;
        }

        return [
            'precalculation' => LeadPrecalculationPayloadNormalizer::normalize($raw),
            'snapshot_at' => $snapshotAt,
            'lead_id' => $order->lead_id ? (int) $order->lead_id : null,
        ];
    }
}
