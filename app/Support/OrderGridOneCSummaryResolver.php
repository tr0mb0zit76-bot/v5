<?php

namespace App\Support;

use App\Models\FleetDriver;
use App\Models\FleetVehicle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderGridOneCSummaryResolver
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public function enrich(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $fleetDriverIds = [];
        $fleetVehicleIds = [];
        $legacyDriverIds = [];

        foreach ($rows as $row) {
            $selection = $this->resolveFleetSelection($row);
            if ($selection['fleet_driver_id'] !== null) {
                $fleetDriverIds[$selection['fleet_driver_id']] = true;
            }
            if ($selection['fleet_vehicle_id'] !== null) {
                $fleetVehicleIds[$selection['fleet_vehicle_id']] = true;
            }

            $legacyDriverId = (int) ($row['driver_id'] ?? 0);
            if ($legacyDriverId > 0 && $selection['fleet_driver_id'] === null) {
                $legacyDriverIds[$legacyDriverId] = true;
            }
        }

        $fleetDrivers = $this->loadFleetDrivers(array_keys($fleetDriverIds));
        $fleetVehicles = $this->loadFleetVehicles(array_keys($fleetVehicleIds));
        $legacyDrivers = $this->loadLegacyDrivers(array_keys($legacyDriverIds));

        return $rows->map(function (array $row) use ($fleetDrivers, $fleetVehicles, $legacyDrivers): array {
            $selection = $this->resolveFleetSelection($row);
            $driverName = null;
            $vehiclePlates = null;

            if ($selection['fleet_driver_id'] !== null) {
                $driverName = $fleetDrivers[$selection['fleet_driver_id']] ?? null;
            } elseif ((int) ($row['driver_id'] ?? 0) > 0) {
                $driverName = $legacyDrivers[(int) $row['driver_id']] ?? null;
            }

            if ($selection['fleet_vehicle_id'] !== null) {
                $vehiclePlates = $fleetVehicles[$selection['fleet_vehicle_id']] ?? null;
            }

            $row['one_c_summary'] = OrderOneCSummaryFormatter::format(
                isset($row['order_number']) ? (string) $row['order_number'] : null,
                isset($row['loading_point']) ? (string) $row['loading_point'] : null,
                isset($row['last_unloading_point']) ? (string) $row['last_unloading_point'] : null,
                $driverName,
                $vehiclePlates,
            );

            unset($row['performers']);

            return $row;
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{fleet_vehicle_id: ?int, fleet_driver_id: ?int}
     */
    private function resolveFleetSelection(array $row): array
    {
        $performers = $row['performers'] ?? null;
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
     * @return array<int, string>
     */
    private function loadFleetDrivers(array $ids): array
    {
        if ($ids === [] || ! Schema::hasTable('fleet_drivers')) {
            return [];
        }

        return FleetDriver::query()
            ->whereIn('id', $ids)
            ->get(['id', 'full_name'])
            ->mapWithKeys(fn (FleetDriver $driver): array => [
                $driver->id => trim((string) $driver->full_name),
            ])
            ->filter(fn (string $name): bool => $name !== '')
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function loadFleetVehicles(array $ids): array
    {
        if ($ids === [] || ! Schema::hasTable('fleet_vehicles')) {
            return [];
        }

        return FleetVehicle::query()
            ->whereIn('id', $ids)
            ->get(['id', 'tractor_plate', 'trailer_plate'])
            ->mapWithKeys(fn (FleetVehicle $vehicle): array => [
                $vehicle->id => OrderOneCSummaryFormatter::vehiclePlatesLabel(
                    $vehicle->tractor_plate,
                    $vehicle->trailer_plate,
                ) ?? '',
            ])
            ->filter(fn (string $plates): bool => $plates !== '')
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function loadLegacyDrivers(array $ids): array
    {
        if ($ids === [] || ! Schema::hasTable('drivers')) {
            return [];
        }

        return DB::table('drivers')
            ->select('id', 'first_name', 'last_name', 'patronymic')
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(function (object $driver): array {
                $name = trim(implode(' ', array_filter([
                    $driver->last_name ?? null,
                    $driver->first_name ?? null,
                    $driver->patronymic ?? null,
                ])));

                return [(int) $driver->id => $name];
            })
            ->filter(fn (string $name): bool => $name !== '')
            ->all();
    }
}
