<?php

namespace App\Services\Epd;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EtrnDraftBuilder
{
    /**
     * @return array{payload: array<string, mixed>, missing_required_fields: list<string>}
     */
    public function build(Order $order): array
    {
        $payload = [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'order_date' => optional($order->order_date)?->toDateString(),
                'loading_date' => optional($order->loading_date)?->toDateString(),
                'unloading_date' => optional($order->unloading_date)?->toDateString(),
            ],
            'parties' => [
                'customer' => [
                    'id' => $order->customer_id,
                    'name' => $order->client?->name,
                    'inn' => $order->client?->inn,
                ],
                'carrier' => [
                    'id' => $order->carrier_id,
                    'name' => $order->carrier?->name,
                    'inn' => $order->carrier?->inn,
                ],
            ],
            'route_points' => $order->relationLoaded('routePoints')
                ? $order->routePoints->map(fn ($point): array => [
                    'type' => $point->type,
                    'address' => $point->address,
                    'planned_date' => optional($point->planned_date)?->toDateString(),
                    'planned_time_from' => $point->planned_time_from,
                    'planned_time_to' => $point->planned_time_to,
                ])->values()->all()
                : [],
            'cargo_items' => $order->relationLoaded('cargoItems')
                ? $order->cargoItems->map(fn ($cargo): array => [
                    'title' => $cargo->title,
                    'weight' => $cargo->weight,
                    'volume' => $cargo->volume,
                    'package_count' => $cargo->package_count,
                ])->values()->all()
                : [],
            'driver' => $this->driverPayload($order),
        ];

        return [
            'payload' => $payload,
            'missing_required_fields' => $this->missingFields($payload),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function missingFields(array $payload): array
    {
        $required = [
            'order.order_number',
            'order.loading_date',
            'order.unloading_date',
            'parties.customer.name',
            'parties.carrier.name',
        ];

        $missing = [];
        foreach ($required as $path) {
            $value = data_get($payload, $path);
            if ($value === null || $value === '') {
                $missing[] = $path;
            }
        }

        return $missing;
    }

    /**
     * @return array{id: mixed, full_name: ?string, phone: ?string, license_number: ?string}
     */
    private function driverPayload(Order $order): array
    {
        $driverId = (int) ($order->driver_id ?? 0);

        if ($driverId <= 0 || ! Schema::hasTable('drivers')) {
            return [
                'id' => $order->driver_id,
                'full_name' => null,
                'phone' => null,
                'license_number' => null,
            ];
        }

        $driver = DB::table('drivers')->where('id', $driverId)->first();

        if ($driver === null) {
            return [
                'id' => $order->driver_id,
                'full_name' => null,
                'phone' => null,
                'license_number' => null,
            ];
        }

        $fullName = trim(implode(' ', array_filter([
            $driver->last_name ?? null,
            $driver->first_name ?? null,
            $driver->patronymic ?? null,
        ])));

        return [
            'id' => $order->driver_id,
            'full_name' => $fullName !== '' ? $fullName : null,
            'phone' => $driver->phone ?? null,
            'license_number' => $driver->license_number ?? null,
        ];
    }
}
