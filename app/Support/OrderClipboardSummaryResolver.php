<?php

namespace App\Support;

use App\Models\Order;
use App\Models\RoutePoint;
use Illuminate\Support\Collection;

class OrderClipboardSummaryResolver
{
    public function __construct(
        private readonly OrderFleetTransportDetailsResolver $transportDetails,
    ) {}

    /**
     * @param  Collection<int, Order>  $orders
     * @return array<int, string>
     */
    public function mapForOrders(Collection $orders): array
    {
        if ($orders->isEmpty()) {
            return [];
        }

        $orders->each(function (Order $order): void {
            $order->loadMissing([
                'client:id,name',
                'legs' => fn ($query) => $query->orderBy('sequence'),
                'legs.routePoints' => fn ($query) => $query->orderBy('sequence'),
            ]);
        });

        $summaries = [];

        foreach ($orders as $order) {
            [$loadingCity, $unloadingCity] = $this->resolveRouteCities($order);
            $transport = $this->transportDetails->resolveForOrder($order);

            $summaries[(int) $order->id] = OrderClipboardSummaryFormatter::format(
                $order->company_code,
                $order->client?->name,
                filled($order->order_number) ? (string) $order->order_number : null,
                $order->order_date,
                $order->customer_rate,
                $order->customer_payment_form,
                $loadingCity,
                $unloadingCity,
                $transport['tractor_brand'],
                $transport['tractor_plate'],
                $transport['trailer_brand'],
                $transport['trailer_plate'],
                $transport['driver_name'],
            );
        }

        return $summaries;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveRouteCities(Order $order): array
    {
        $points = $order->legs
            ->sortBy('sequence')
            ->flatMap(fn ($leg) => $leg->routePoints->sortBy('sequence'))
            ->values();

        $loading = $points->first(fn (RoutePoint $point): bool => $point->type === 'loading');
        $unloading = $points->filter(fn (RoutePoint $point): bool => $point->type === 'unloading')->last();

        return [
            $loading !== null ? $this->routePointCityLabel($loading) : null,
            $unloading !== null ? $this->routePointCityLabel($unloading) : null,
        ];
    }

    private function routePointCityLabel(RoutePoint $point): ?string
    {
        $normalized = RoutePointNormalizedData::resolveForWizard($point);
        $city = trim((string) ($normalized['city'] ?? ''));

        if ($city !== '') {
            return $city;
        }

        $address = trim((string) ($point->address ?? ''));

        return $address !== '' ? $address : null;
    }
}
