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
            $portalSubmission = $this->resolvePortalSubmission($row);
            $driverName = null;

            if ($selection['fleet_driver_id'] !== null) {
                $driverName = $fleetDrivers[$selection['fleet_driver_id']] ?? null;
            } elseif ((int) ($row['driver_id'] ?? 0) > 0) {
                $driverName = $legacyDrivers[(int) $row['driver_id']] ?? null;
            } elseif (is_array($portalSubmission)) {
                $driverName = trim((string) ($portalSubmission['driver_full_name'] ?? ''));
                if ($driverName === '') {
                    $driverName = null;
                }
            }

            $vehicle = $selection['fleet_vehicle_id'] !== null
                ? ($fleetVehicles[$selection['fleet_vehicle_id']] ?? null)
                : null;

            if ($vehicle === null && is_array($portalSubmission)) {
                $vehicle = [
                    'tractor_brand' => filled($portalSubmission['tractor_brand'] ?? null)
                        ? (string) $portalSubmission['tractor_brand']
                        : null,
                    'tractor_plate' => filled($portalSubmission['tractor_plate'] ?? null)
                        ? (string) $portalSubmission['tractor_plate']
                        : null,
                    'trailer_brand' => filled($portalSubmission['trailer_brand'] ?? null)
                        ? (string) $portalSubmission['trailer_brand']
                        : null,
                    'trailer_plate' => filled($portalSubmission['trailer_plate'] ?? null)
                        ? (string) $portalSubmission['trailer_plate']
                        : null,
                ];
            }

            $summary = OrderClipboardSummaryFormatter::format(
                isset($row['company_code']) ? (string) $row['company_code'] : null,
                isset($row['customer_name']) ? (string) $row['customer_name'] : null,
                isset($row['order_number']) ? (string) $row['order_number'] : null,
                $row['order_date'] ?? null,
                $row['customer_rate'] ?? null,
                isset($row['customer_payment_form']) ? (string) $row['customer_payment_form'] : null,
                isset($row['loading_point']) ? (string) $row['loading_point'] : null,
                isset($row['last_unloading_point']) ? (string) $row['last_unloading_point'] : null,
                $vehicle['tractor_brand'] ?? null,
                $vehicle['tractor_plate'] ?? null,
                $vehicle['trailer_brand'] ?? null,
                $vehicle['trailer_plate'] ?? null,
                $driverName,
            );

            $row['one_c_summary'] = $summary;
            $row['clipboard_summary'] = $summary;

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

            if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                foreach ($performer['split_carriers'] as $slot) {
                    if (! is_array($slot)) {
                        continue;
                    }

                    $vehicleId = isset($slot['fleet_vehicle_id']) && $slot['fleet_vehicle_id'] !== null
                        ? (int) $slot['fleet_vehicle_id']
                        : null;
                    $driverId = isset($slot['fleet_driver_id']) && $slot['fleet_driver_id'] !== null
                        ? (int) $slot['fleet_driver_id']
                        : null;

                    if ($vehicleId !== null || $driverId !== null) {
                        return [
                            'fleet_vehicle_id' => $vehicleId,
                            'fleet_driver_id' => $driverId,
                        ];
                    }
                }

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
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function resolvePortalSubmission(array $row): ?array
    {
        $performers = $row['performers'] ?? null;
        if (is_string($performers)) {
            $decoded = json_decode($performers, true);
            $performers = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($performers)) {
            return null;
        }

        foreach ($performers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                foreach ($performer['split_carriers'] as $slot) {
                    if (! is_array($slot)) {
                        continue;
                    }

                    $submission = $slot['carrier_portal_submission'] ?? null;
                    if (CarrierPortalSubmission::isUsable(is_array($submission) ? $submission : null)) {
                        return $submission;
                    }
                }

                continue;
            }

            $submission = $performer['carrier_portal_submission'] ?? null;
            if (CarrierPortalSubmission::isUsable(is_array($submission) ? $submission : null)) {
                return $submission;
            }
        }

        return null;
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
