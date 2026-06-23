<?php

namespace App\Support;

use App\Models\Order;

final class OrderTransportTypeResolver
{
    public const LABEL_OWN_FLEET = 'Собственный парк';

    public const LABEL_HIRED = 'Наёмный';

    public const LABEL_MIXED = 'Смешанный';

    public const LABEL_UNKNOWN = '—';

    public function labelForOrder(Order $order): string
    {
        $hasOwnFleet = false;
        $hasHired = false;

        foreach ($this->performerRows($order) as $row) {
            if (OwnFleetCatalog::performerRowIsOwnFleet($row)) {
                $hasOwnFleet = true;

                continue;
            }

            if ($this->rowHasHiredCarrier($row)) {
                $hasHired = true;
            }
        }

        if ($hasOwnFleet && $hasHired) {
            return self::LABEL_MIXED;
        }

        if ($hasOwnFleet) {
            return self::LABEL_OWN_FLEET;
        }

        if ($hasHired || $order->carrier_id !== null) {
            return self::LABEL_HIRED;
        }

        return self::LABEL_UNKNOWN;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function performerRows(Order $order): array
    {
        $performers = is_array($order->performers) ? $order->performers : [];
        $rows = [];

        foreach ($performers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            $rows[] = $performer;

            foreach ($performer['split_carriers'] ?? [] as $slot) {
                if (is_array($slot)) {
                    $rows[] = $slot;
                }
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowHasHiredCarrier(array $row): bool
    {
        if (filled($row['contractor_id'] ?? null)) {
            return true;
        }

        $name = trim((string) ($row['contractor_name'] ?? ''));

        return $name !== '' && $name !== OwnFleetCatalog::CONTRACTOR_NAME;
    }
}
