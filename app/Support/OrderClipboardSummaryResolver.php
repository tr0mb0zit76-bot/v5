<?php

namespace App\Support;

use App\Models\FleetDriver;
use App\Models\FleetVehicle;
use App\Models\Order;
use App\Models\RoutePoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderClipboardSummaryResolver
{
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
                'legs' => fn ($query) => $query->orderBy('sequence'),
                'legs.routePoints' => fn ($query) => $query->orderBy('sequence'),
            ]);
        });

        $fleetDriverIds = [];
        $fleetVehicleIds = [];
        $legacyDriverIds = [];

        foreach ($orders as $order) {
            $selection = $this->resolveFleetSelection($order);
            if ($selection['fleet_driver_id'] !== null) {
                $fleetDriverIds[$selection['fleet_driver_id']] = true;
            }
            if ($selection['fleet_vehicle_id'] !== null) {
                $fleetVehicleIds[$selection['fleet_vehicle_id']] = true;
            }

            $legacyDriverId = (int) ($order->driver_id ?? 0);
            if ($legacyDriverId > 0 && $selection['fleet_driver_id'] === null) {
                $legacyDriverIds[$legacyDriverId] = true;
            }
        }

        $fleetDrivers = $this->loadFleetDrivers(array_keys($fleetDriverIds));
        $fleetVehicles = $this->loadFleetVehicles(array_keys($fleetVehicleIds));
        $legacyDrivers = $this->loadLegacyDrivers(array_keys($legacyDriverIds));

        $summaries = [];

        foreach ($orders as $order) {
            $selection = $this->resolveFleetSelection($order);
            [$loadingCity, $unloadingCity] = $this->resolveRouteCities($order);

            $driverName = null;
            $driverPassport = null;

            if ($selection['fleet_driver_id'] !== null) {
                $driverName = $fleetDrivers[$selection['fleet_driver_id']]['name'] ?? null;
                $driverPassport = $fleetDrivers[$selection['fleet_driver_id']]['passport'] ?? null;
            } elseif ((int) ($order->driver_id ?? 0) > 0) {
                $legacy = $legacyDrivers[(int) $order->driver_id] ?? null;
                $driverName = $legacy['name'] ?? null;
                $driverPassport = $legacy['passport'] ?? null;
            }

            $vehicle = $selection['fleet_vehicle_id'] !== null
                ? ($fleetVehicles[$selection['fleet_vehicle_id']] ?? null)
                : null;

            $summaries[(int) $order->id] = OrderClipboardSummaryFormatter::format(
                $loadingCity,
                $unloadingCity,
                $vehicle['tractor_brand'] ?? null,
                $vehicle['tractor_plate'] ?? null,
                $vehicle['trailer_brand'] ?? null,
                $vehicle['trailer_plate'] ?? null,
                $driverName,
                $driverPassport,
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

    /**
     * @param  array<string, mixed>  $order
     * @return array{fleet_vehicle_id: ?int, fleet_driver_id: ?int}
     */
    private function resolveFleetSelection(Order $order): array
    {
        $performers = $order->performers;
        if (is_string($performers)) {
            $decoded = json_decode($performers, true);
            $performers = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($performers)) {
            $performers = [];
        }

        foreach ($performers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            $vehicleId = isset($performer['fleet_vehicle_id']) && $performer['fleet_vehicle_id'] !== null
                ? (int) $performer['fleet_vehicle_id']
                : null;
            $driverId = isset($performer['fleet_driver_id']) && $performer['fleet_driver_id'] !== null
                ? (int) $performer['fleet_driver_id']
                : null;

            if ($vehicleId !== null || $driverId !== null) {
                return [
                    'fleet_vehicle_id' => $vehicleId,
                    'fleet_driver_id' => $driverId,
                ];
            }
        }

        return [
            'fleet_vehicle_id' => null,
            'fleet_driver_id' => null,
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{name: string, passport: ?string}>
     */
    private function loadFleetDrivers(array $ids): array
    {
        if ($ids === [] || ! Schema::hasTable('fleet_drivers')) {
            return [];
        }

        return FleetDriver::query()
            ->whereIn('id', $ids)
            ->get(['id', 'full_name', 'passport_series', 'passport_number', 'passport_issued_by', 'passport_issued_at'])
            ->mapWithKeys(function (FleetDriver $driver): array {
                $passportParts = array_filter([
                    $driver->passport_series,
                    $driver->passport_number,
                    $driver->passport_issued_by,
                    $driver->passport_issued_at?->format('d.m.Y'),
                ]);

                return [
                    $driver->id => [
                        'name' => trim((string) $driver->full_name),
                        'passport' => $passportParts !== [] ? implode(' ', $passportParts) : null,
                    ],
                ];
            })
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{tractor_brand: ?string, tractor_plate: ?string, trailer_brand: ?string, trailer_plate: ?string}>
     */
    private function loadFleetVehicles(array $ids): array
    {
        if ($ids === [] || ! Schema::hasTable('fleet_vehicles')) {
            return [];
        }

        return FleetVehicle::query()
            ->whereIn('id', $ids)
            ->get(['id', 'tractor_brand', 'tractor_plate', 'trailer_brand', 'trailer_plate'])
            ->mapWithKeys(fn (FleetVehicle $vehicle): array => [
                $vehicle->id => [
                    'tractor_brand' => $vehicle->tractor_brand,
                    'tractor_plate' => $vehicle->tractor_plate,
                    'trailer_brand' => $vehicle->trailer_brand,
                    'trailer_plate' => $vehicle->trailer_plate,
                ],
            ])
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{name: string, passport: ?string}>
     */
    private function loadLegacyDrivers(array $ids): array
    {
        if ($ids === [] || ! Schema::hasTable('drivers')) {
            return [];
        }

        return DB::table('drivers')
            ->select('id', 'first_name', 'last_name', 'patronymic', 'metadata')
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(function (object $driver): array {
                $name = trim(implode(' ', array_filter([
                    $driver->last_name ?? null,
                    $driver->first_name ?? null,
                    $driver->patronymic ?? null,
                ])));

                $metadata = is_string($driver->metadata) ? json_decode($driver->metadata, true) : $driver->metadata;
                $passportData = is_array($metadata)
                    ? data_get($metadata, 'passport_data', data_get($metadata, 'passport'))
                    : null;

                return [
                    (int) $driver->id => [
                        'name' => $name,
                        'passport' => is_scalar($passportData) ? trim((string) $passportData) : null,
                    ],
                ];
            })
            ->all();
    }
}
