<?php

namespace App\Services\Leads;

use App\Models\Lead;
use App\Models\Order;
use App\Support\CarrierRateFromFinancialTerms;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Фаза 5 CI — ориентиры ставок по истории сделок (без ML).
 */
class LeadRoutePriceBenchmarkService
{
    /**
     * @return array{
     *     available: bool,
     *     sample_size: int,
     *     loading_location: string|null,
     *     unloading_location: string|null,
     *     customer_rate: array{min: float|null, avg: float|null, max: float|null, currency: string|null},
     *     carrier_rate: array{min: float|null, avg: float|null, max: float|null, currency: string|null},
     *     orders: list<array{id: int, order_number: string|null, customer_rate: float|null, carrier_rate: float|null, completed_at: string|null}>
     * }|null
     */
    public function benchmarkForLead(Lead $lead, int $limit = 8): ?array
    {
        if (! Schema::hasTable('orders')) {
            return null;
        }

        $loading = $this->normalizeLocation($lead->loading_location);
        $unloading = $this->normalizeLocation($lead->unloading_location);

        if ($loading === null && $unloading === null) {
            return [
                'available' => false,
                'sample_size' => 0,
                'loading_location' => null,
                'unloading_location' => null,
                'customer_rate' => ['min' => null, 'avg' => null, 'max' => null, 'currency' => $lead->target_currency ?? 'RUB'],
                'carrier_rate' => ['min' => null, 'avg' => null, 'max' => null, 'currency' => $lead->target_currency ?? 'RUB'],
                'orders' => [],
            ];
        }

        $query = Order::query()
            ->when(Schema::hasColumn('orders', 'deleted_at'), fn ($builder) => $builder->whereNull('deleted_at'))
            ->where(function ($builder) use ($loading, $unloading): void {
                if ($loading !== null) {
                    $builder->where(function ($match) use ($loading): void {
                        if (Schema::hasColumn('orders', 'loading_city')) {
                            $match->where('loading_city', 'like', '%'.$loading.'%');
                        }

                        if (Schema::hasColumn('orders', 'loading_location')) {
                            if (Schema::hasColumn('orders', 'loading_city')) {
                                $match->orWhere('loading_location', 'like', '%'.$loading.'%');
                            } else {
                                $match->where('loading_location', 'like', '%'.$loading.'%');
                            }
                        }
                    });
                }

                if ($unloading !== null) {
                    $builder->where(function ($match) use ($unloading): void {
                        if (Schema::hasColumn('orders', 'unloading_city')) {
                            $match->where('unloading_city', 'like', '%'.$unloading.'%');
                        }

                        if (Schema::hasColumn('orders', 'unloading_location')) {
                            if (Schema::hasColumn('orders', 'unloading_city')) {
                                $match->orWhere('unloading_location', 'like', '%'.$unloading.'%');
                            } else {
                                $match->where('unloading_location', 'like', '%'.$unloading.'%');
                            }
                        }
                    });
                }
            })
            ->when(Schema::hasColumn('orders', 'customer_rate'), fn ($builder) => $builder->whereNotNull('customer_rate'))
            ->orderByDesc('id')
            ->limit(max(20, $limit * 3));

        if ($lead->id) {
            $query->where(function ($builder) use ($lead): void {
                $builder->whereNull('lead_id')->orWhere('lead_id', '!=', $lead->id);
            });
        }

        $selectColumns = array_values(array_filter([
            'id',
            'order_number',
            Schema::hasColumn('orders', 'customer_rate') ? 'customer_rate' : null,
            Schema::hasColumn('orders', 'customer_currency') ? 'customer_currency' : null,
            Schema::hasColumn('orders', 'carrier_rate') ? 'carrier_rate' : null,
            'updated_at',
        ]));

        $orders = $query->get($selectColumns)->take($limit);

        $carrierRatesByOrderId = Schema::hasColumn('orders', 'carrier_rate')
            ? collect()
            : CarrierRateFromFinancialTerms::sumsByOrderId(
                $orders->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
            );

        $customerRates = $orders->pluck('customer_rate')->filter(fn ($value): bool => $value !== null)->map(fn ($value): float => (float) $value);
        $carrierRates = $orders->map(function (Order $order) use ($carrierRatesByOrderId): ?float {
            if (Schema::hasColumn('orders', 'carrier_rate')) {
                return $order->carrier_rate !== null ? (float) $order->carrier_rate : null;
            }

            $resolved = $carrierRatesByOrderId->get($order->id);

            return $resolved !== null ? (float) $resolved : null;
        })->filter(fn (?float $value): bool => $value !== null)->map(fn (float $value): float => $value);

        return [
            'available' => $customerRates->isNotEmpty() || $carrierRates->isNotEmpty(),
            'sample_size' => $orders->count(),
            'loading_location' => $lead->loading_location,
            'unloading_location' => $lead->unloading_location,
            'customer_rate' => $this->stats($customerRates, $lead->target_currency ?? 'RUB'),
            'carrier_rate' => $this->stats($carrierRates, $lead->target_currency ?? 'RUB'),
            'orders' => $orders->map(function (Order $order) use ($carrierRatesByOrderId): array {
                $carrierRate = null;
                if (Schema::hasColumn('orders', 'carrier_rate')) {
                    $carrierRate = $order->carrier_rate !== null ? (float) $order->carrier_rate : null;
                } else {
                    $resolved = $carrierRatesByOrderId->get($order->id);
                    $carrierRate = $resolved !== null ? (float) $resolved : null;
                }

                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_rate' => $order->customer_rate !== null ? (float) $order->customer_rate : null,
                    'carrier_rate' => $carrierRate,
                    'completed_at' => $order->updated_at?->toDateString(),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, float>  $values
     * @return array{min: float|null, avg: float|null, max: float|null, currency: string}
     */
    private function stats($values, string $currency): array
    {
        if ($values->isEmpty()) {
            return [
                'min' => null,
                'avg' => null,
                'max' => null,
                'currency' => $currency,
            ];
        }

        return [
            'min' => round((float) $values->min(), 2),
            'avg' => round((float) $values->avg(), 2),
            'max' => round((float) $values->max(), 2),
            'currency' => $currency,
        ];
    }

    private function normalizeLocation(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed) < 3) {
            return null;
        }

        return mb_substr($trimmed, 0, 80);
    }
}
