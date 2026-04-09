<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\FinancialTerm;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Сумма ставок перевозчика по плечам из `financial_terms.contractors_costs`, если колонка `orders.carrier_rate` отсутствует или как уточнение.
 */
final class CarrierRateFromFinancialTerms
{
    /**
     * Ставка перевозчика для расчёта маржи (delta) и KPI: совпадает с тем, что показывает грид —
     * при непустом `contractors_costs` берётся сумма по плечам, иначе `orders.carrier_rate`.
     */
    public static function resolveForOrder(Order $order): float
    {
        $fromOrder = (float) ($order->getAttribute('carrier_rate') ?? 0);

        if (! Schema::hasTable('financial_terms')) {
            return $fromOrder;
        }

        $payload = null;
        if ($order->relationLoaded('financialTerms')) {
            $payload = $order->financialTerms->first()?->contractors_costs;
        } else {
            $payload = FinancialTerm::query()->where('order_id', $order->id)->value('contractors_costs');
        }

        $fromFt = self::sumContractorsCostsAmounts($payload);

        return $fromFt !== null ? $fromFt : $fromOrder;
    }

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
