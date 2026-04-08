<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Сумма ставок перевозчика по плечам из `financial_terms.contractors_costs`, если колонка `orders.carrier_rate` отсутствует или как уточнение.
 */
final class CarrierRateFromFinancialTerms
{
    /**
     * @param  list<int>  $orderIds
     * @return Collection<int, float|null>
     */
    public static function sumsByOrderId(array $orderIds): Collection
    {
        if ($orderIds === [] || ! Schema::hasTable('financial_terms')) {
            return collect();
        }

        $rows = DB::table('financial_terms')
            ->whereIn('order_id', $orderIds)
            ->get(['order_id', 'contractors_costs']);

        return $rows->mapWithKeys(function (object $row): array {
            $sum = self::sumContractorsCostsAmounts($row->contractors_costs);

            return [(int) $row->order_id => $sum];
        });
    }

    public static function sumContractorsCostsAmounts(mixed $payload): ?float
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        if (is_array($payload)) {
            $costs = $payload;
        } else {
            $decoded = json_decode((string) $payload, true);
            $costs = is_array($decoded) ? $decoded : [];
        }

        if ($costs === []) {
            return null;
        }

        $sum = collect($costs)->sum(fn (array $c): float => (float) ($c['amount'] ?? 0));

        return round($sum, 2);
    }
}
