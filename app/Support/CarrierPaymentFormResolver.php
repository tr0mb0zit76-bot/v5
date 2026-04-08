<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\FinancialTerm;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class CarrierPaymentFormResolver
{
    /**
     * @param  list<array<string, mixed>>  $contractorsCosts
     */
    public static function fromContractorsCostsArray(array $contractorsCosts): ?string
    {
        if ($contractorsCosts === []) {
            return null;
        }

        $forms = collect($contractorsCosts)
            ->pluck('payment_form')
            ->filter()
            ->unique()
            ->values();

        if ($forms->isEmpty()) {
            return null;
        }

        return $forms->count() === 1 ? $forms->first() : 'mixed';
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
     * Колонка `orders.carrier_payment_form` может отсутствовать; тогда порядок источников:
     * строки `leg_costs` по ногам заказа, затем JSON `contractors_costs` в `financial_terms`.
     */
    public static function forOrder(Order $order): ?string
    {
        if (Schema::hasColumn('orders', 'carrier_payment_form')) {
            $direct = $order->getAttribute('carrier_payment_form');
            if (! blank($direct)) {
                return $direct;
            }
        }

        $fromLegs = self::fromLegCosts($order);
        if ($fromLegs !== null) {
            return $fromLegs;
        }

        if (! Schema::hasTable('financial_terms')) {
            return null;
        }

        $costsPayload = null;

        if ($order->relationLoaded('financialTerms')) {
            $first = $order->financialTerms->first();
            $costsPayload = $first?->contractors_costs;
        } else {
            $costsPayload = FinancialTerm::query()
                ->where('order_id', $order->id)
                ->value('contractors_costs');
        }

        return self::fromContractorsCostsPayload($costsPayload);
    }

    /**
     * Одна уникальная непустая форма по всем ногам — эта форма; несколько разных — `mixed`.
     */
    private static function fromLegCosts(Order $order): ?string
    {
        if (! Schema::hasTable('leg_costs')) {
            return null;
        }

        if (! $order->relationLoaded('legs')) {
            $order->load('legs.cost');
        }

        $forms = $order->legs
            ->map(fn ($leg) => $leg->cost?->payment_form)
            ->filter()
            ->unique()
            ->values();

        if ($forms->isEmpty()) {
            return null;
        }

        return $forms->count() === 1 ? (string) $forms->first() : 'mixed';
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
