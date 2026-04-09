<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\FinancialTerm;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Текст «условий оплаты перевозчика» из {@see FinancialTerm::contractors_costs} (как в мастере заказа).
 */
final class CarrierPaymentTermResolver
{
    /**
     * @param  list<array<string, mixed>>  $contractorsCosts
     */
    public static function fromContractorsCostsArray(array $contractorsCosts): ?string
    {
        if ($contractorsCosts === []) {
            return null;
        }

        $summaries = collect($contractorsCosts)
            ->map(fn (array $cost): string => PaymentScheduleSummaryFormatter::format((array) ($cost['payment_schedule'] ?? [])))
            ->unique()
            ->values();

        return $summaries->count() === 1 ? (string) $summaries->first() : 'см. этапы';
    }

    public static function fromContractorsCostsPayload(mixed $payload): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        if (is_array($payload)) {
            return self::fromContractorsCostsArray($payload);
        }

        $decoded = json_decode((string) $payload, true);

        return is_array($decoded) ? self::fromContractorsCostsArray($decoded) : null;
    }

    /**
     * @param  list<int>  $orderIds
     * @return Collection<int, string|null>
     */
    public static function mapForOrderIds(array $orderIds): Collection
    {
        if ($orderIds === [] || ! Schema::hasTable('financial_terms')) {
            return collect();
        }

        $rows = FinancialTerm::query()
            ->whereIn('order_id', $orderIds)
            ->orderByDesc('id')
            ->get(['order_id', 'contractors_costs']);

        return $rows->unique('order_id')->mapWithKeys(function (FinancialTerm $row): array {
            return [
                (int) $row->order_id => self::fromContractorsCostsPayload($row->contractors_costs),
            ];
        });
    }
}
