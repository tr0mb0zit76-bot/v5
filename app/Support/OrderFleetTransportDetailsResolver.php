<?php

namespace App\Support;

use App\Models\FleetDriver;
use App\Models\FleetTrip;
use App\Models\FleetVehicle;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class OrderFleetTransportDetailsResolver
{
    /**
     * @return array{
     *     fleet_vehicle_id: ?int,
     *     fleet_driver_id: ?int,
     *     tractor_brand: ?string,
     *     tractor_plate: ?string,
     *     trailer_brand: ?string,
     *     trailer_plate: ?string,
     *     driver_name: ?string
     * }
     */
    public function resolveForOrder(Order $order): array
    {
        $performers = $this->collectPerformerRows($order);
        $selection = $this->resolveFleetSelection($performers);
        $portalSubmission = $this->resolvePortalSubmission($performers);

        $driverName = null;
        if ($selection['fleet_driver_id'] !== null) {
            $driverName = $this->loadFleetDriverName($selection['fleet_driver_id']);
        } elseif ((int) ($order->driver_id ?? 0) > 0) {
            $driverName = $this->loadLegacyDriverName((int) $order->driver_id);
        } elseif (is_array($portalSubmission)) {
            $driverName = trim((string) ($portalSubmission['driver_full_name'] ?? ''));
            $driverName = $driverName !== '' ? $driverName : null;
        }

        $vehicle = $selection['fleet_vehicle_id'] !== null
            ? $this->loadFleetVehicle($selection['fleet_vehicle_id'])
            : null;

        if ($vehicle === null && is_array($portalSubmission)) {
            $vehicle = $this->vehicleFromPortalSubmission($portalSubmission);
        }

        if ($vehicle === null) {
            $vehicle = $this->vehicleFromOrderMetadata($order);
        }

        return [
            'fleet_vehicle_id' => $selection['fleet_vehicle_id'],
            'fleet_driver_id' => $selection['fleet_driver_id'],
            'tractor_brand' => $vehicle['tractor_brand'] ?? null,
            'tractor_plate' => $vehicle['tractor_plate'] ?? null,
            'trailer_brand' => $vehicle['trailer_brand'] ?? null,
            'trailer_plate' => $vehicle['trailer_plate'] ?? null,
            'driver_name' => $driverName,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{
     *     fleet_vehicle_id: ?int,
     *     fleet_driver_id: ?int,
     *     tractor_brand: ?string,
     *     tractor_plate: ?string,
     *     trailer_brand: ?string,
     *     trailer_plate: ?string,
     *     driver_name: ?string
     * }
     */
    public function resolveForGridRow(array $row): array
    {
        $performers = $this->normalizePerformers($row['performers'] ?? null);
        $selection = $this->resolveFleetSelection($performers);
        $portalSubmission = $this->resolvePortalSubmission($performers);

        $driverName = null;
        if ($selection['fleet_driver_id'] !== null) {
            $driverName = $this->loadFleetDriverName($selection['fleet_driver_id']);
        } elseif ((int) ($row['driver_id'] ?? 0) > 0) {
            $driverName = $this->loadLegacyDriverName((int) $row['driver_id']);
        } elseif (is_array($portalSubmission)) {
            $driverName = trim((string) ($portalSubmission['driver_full_name'] ?? ''));
            $driverName = $driverName !== '' ? $driverName : null;
        }

        $vehicle = $selection['fleet_vehicle_id'] !== null
            ? $this->loadFleetVehicle($selection['fleet_vehicle_id'])
            : null;

        if ($vehicle === null && is_array($portalSubmission)) {
            $vehicle = $this->vehicleFromPortalSubmission($portalSubmission);
        }

        return [
            'fleet_vehicle_id' => $selection['fleet_vehicle_id'],
            'fleet_driver_id' => $selection['fleet_driver_id'],
            'tractor_brand' => $vehicle['tractor_brand'] ?? null,
            'tractor_plate' => $vehicle['tractor_plate'] ?? null,
            'trailer_brand' => $vehicle['trailer_brand'] ?? null,
            'trailer_plate' => $vehicle['trailer_plate'] ?? null,
            'driver_name' => $driverName,
        ];
    }

    /**
     * Исполнители из `order_legs.metadata` для грида заказов (на проде колонки `orders.performers` может не быть).
     *
     * @param  list<int>  $orderIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function loadPerformerRowsByOrderIds(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $orderIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($orderIds === [] || ! Schema::hasTable('order_legs') || ! Schema::hasColumn('order_legs', 'metadata')) {
            return [];
        }

        $rows = DB::table('order_legs')
            ->whereIn('order_id', $orderIds)
            ->orderBy('order_id')
            ->orderBy('sequence')
            ->get(['order_id', 'metadata']);

        $map = [];

        foreach ($rows as $leg) {
            $metadata = json_decode((string) ($leg->metadata ?? ''), true);
            $performer = is_array($metadata['performer'] ?? null) ? $metadata['performer'] : null;
            if (! is_array($performer)) {
                continue;
            }

            $orderId = (int) $leg->order_id;
            $map[$orderId] ??= [];
            $map[$orderId][] = $performer;
        }

        return $map;
    }

    /**
     * @param  list<array<string, mixed>>  $performers
     */
    public function performersContainFleetOrPortalData(array $performers): bool
    {
        foreach ($performers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            $selection = $this->extractFleetIdsFromRow($performer);
            if ($selection['fleet_vehicle_id'] !== null || $selection['fleet_driver_id'] !== null) {
                return true;
            }

            if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                foreach ($performer['split_carriers'] as $slot) {
                    if (! is_array($slot)) {
                        continue;
                    }

                    $slotSelection = $this->extractFleetIdsFromRow($slot);
                    if ($slotSelection['fleet_vehicle_id'] !== null || $slotSelection['fleet_driver_id'] !== null) {
                        return true;
                    }

                    if (CarrierPortalSubmission::isUsable(is_array($slot['carrier_portal_submission'] ?? null) ? $slot['carrier_portal_submission'] : null)) {
                        return true;
                    }
                }
            }

            if (CarrierPortalSubmission::isUsable(is_array($performer['carrier_portal_submission'] ?? null) ? $performer['carrier_portal_submission'] : null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectPerformerRows(Order $order): array
    {
        $rows = [];

        if ($order->relationLoaded('legs')) {
            foreach ($order->legs->sortBy('sequence') as $leg) {
                $performer = is_array($leg->metadata['performer'] ?? null) ? $leg->metadata['performer'] : null;
                if (is_array($performer)) {
                    $rows[] = $performer;
                }
            }
        }

        foreach ($this->normalizePerformers($order->performers) as $performer) {
            $rows[] = $performer;
        }

        if ($rows === []) {
            foreach ($this->normalizePerformers(data_get($order->wizard_state, 'performers')) as $performer) {
                $rows[] = $performer;
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function normalizePerformersForGrid(mixed $performers): array
    {
        return $this->normalizePerformers($performers);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizePerformers(mixed $performers): array
    {
        if (is_string($performers)) {
            $decoded = json_decode($performers, true);
            $performers = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($performers)) {
            return [];
        }

        return array_values(array_filter($performers, static fn (mixed $row): bool => is_array($row)));
    }

    /**
     * @param  list<array<string, mixed>>  $performers
     * @return array{fleet_vehicle_id: ?int, fleet_driver_id: ?int}
     */
    private function resolveFleetSelection(array $performers): array
    {
        foreach ($performers as $performer) {
            if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                foreach ($performer['split_carriers'] as $slot) {
                    if (! is_array($slot)) {
                        continue;
                    }

                    $selection = $this->extractFleetIdsFromRow($slot);
                    if ($selection['fleet_vehicle_id'] !== null || $selection['fleet_driver_id'] !== null) {
                        return $selection;
                    }
                }

                continue;
            }

            $selection = $this->extractFleetIdsFromRow($performer);
            if ($selection['fleet_vehicle_id'] !== null || $selection['fleet_driver_id'] !== null) {
                return $selection;
            }
        }

        return [
            'fleet_vehicle_id' => null,
            'fleet_driver_id' => null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $performers
     * @return array<string, mixed>|null
     */
    private function resolvePortalSubmission(array $performers): ?array
    {
        foreach ($performers as $performer) {
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
     * @param  array<string, mixed>  $row
     * @return array{fleet_vehicle_id: ?int, fleet_driver_id: ?int}
     */
    private function extractFleetIdsFromRow(array $row): array
    {
        $vehicleId = isset($row['fleet_vehicle_id']) && $row['fleet_vehicle_id'] !== null && $row['fleet_vehicle_id'] !== ''
            ? (int) $row['fleet_vehicle_id']
            : null;
        $driverId = isset($row['fleet_driver_id']) && $row['fleet_driver_id'] !== null && $row['fleet_driver_id'] !== ''
            ? (int) $row['fleet_driver_id']
            : null;

        if ($vehicleId === null) {
            $tripId = isset($row['fleet_trip_id']) && $row['fleet_trip_id'] !== null && $row['fleet_trip_id'] !== ''
                ? (int) $row['fleet_trip_id']
                : null;

            if ($tripId !== null) {
                $fromTrip = $this->resolveFleetIdsFromTrip($tripId);
                $vehicleId = $fromTrip['fleet_vehicle_id'];
                if ($driverId === null) {
                    $driverId = $fromTrip['fleet_driver_id'];
                }
            }
        }

        return [
            'fleet_vehicle_id' => $vehicleId,
            'fleet_driver_id' => $driverId,
        ];
    }

    /**
     * @return array{fleet_vehicle_id: ?int, fleet_driver_id: ?int}
     */
    private function resolveFleetIdsFromTrip(int $fleetTripId): array
    {
        if (! Schema::hasTable('fleet_trips')) {
            return [
                'fleet_vehicle_id' => null,
                'fleet_driver_id' => null,
            ];
        }

        /** @var FleetTrip|null $trip */
        $trip = FleetTrip::query()->find($fleetTripId);

        if ($trip === null) {
            return [
                'fleet_vehicle_id' => null,
                'fleet_driver_id' => null,
            ];
        }

        return [
            'fleet_vehicle_id' => $trip->fleet_vehicle_id !== null ? (int) $trip->fleet_vehicle_id : null,
            'fleet_driver_id' => $trip->fleet_driver_id !== null ? (int) $trip->fleet_driver_id : null,
        ];
    }

    /**
     * @return array{tractor_brand: ?string, tractor_plate: ?string, trailer_brand: ?string, trailer_plate: ?string}|null
     */
    private function loadFleetVehicle(int $fleetVehicleId): ?array
    {
        if (! Schema::hasTable('fleet_vehicles')) {
            return null;
        }

        /** @var FleetVehicle|null $vehicle */
        $vehicle = FleetVehicle::query()->find($fleetVehicleId);

        if ($vehicle === null) {
            return null;
        }

        return [
            'tractor_brand' => $vehicle->tractor_brand,
            'tractor_plate' => $vehicle->tractor_plate,
            'trailer_brand' => $vehicle->trailer_brand,
            'trailer_plate' => $vehicle->trailer_plate,
        ];
    }

    /**
     * @param  array<string, mixed>  $portalSubmission
     * @return array{tractor_brand: ?string, tractor_plate: ?string, trailer_brand: ?string, trailer_plate: ?string}
     */
    private function vehicleFromPortalSubmission(array $portalSubmission): array
    {
        return [
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

    /**
     * @return array{tractor_brand: ?string, tractor_plate: ?string, trailer_brand: ?string, trailer_plate: ?string}|null
     */
    private function vehicleFromOrderMetadata(Order $order): ?array
    {
        $orderMetadata = is_array($order->metadata) ? $order->metadata : [];
        $orderWizardState = is_array($order->wizard_state) ? $order->wizard_state : [];

        $tractorPlate = $this->firstFilled([
            data_get($orderWizardState, 'vehicle.tractor_plate'),
            data_get($orderWizardState, 'transport.tractor_plate'),
            data_get($orderWizardState, 'vehicle.number'),
            data_get($orderWizardState, 'transport.vehicle_number'),
            data_get($orderMetadata, 'vehicle.tractor_plate'),
            data_get($orderMetadata, 'vehicle.number'),
            data_get($orderMetadata, 'vehicle_number'),
            data_get($orderMetadata, 'gosnomer'),
        ]);
        $trailerPlate = $this->firstFilled([
            data_get($orderWizardState, 'vehicle.trailer_plate'),
            data_get($orderWizardState, 'transport.trailer_plate'),
            data_get($orderMetadata, 'vehicle.trailer_plate'),
            data_get($orderMetadata, 'trailer_plate'),
            data_get($orderMetadata, 'gosnomer_priz'),
        ]);

        if ($tractorPlate === null && $trailerPlate === null) {
            return null;
        }

        return [
            'tractor_brand' => $this->firstFilled([
                data_get($orderWizardState, 'vehicle.brand'),
                data_get($orderWizardState, 'transport.vehicle_brand'),
                data_get($orderMetadata, 'vehicle.brand'),
                data_get($orderMetadata, 'vehicle_brand'),
            ]),
            'tractor_plate' => $tractorPlate,
            'trailer_brand' => $this->firstFilled([
                data_get($orderWizardState, 'vehicle.trailer_brand'),
                data_get($orderWizardState, 'transport.trailer_brand'),
                data_get($orderMetadata, 'vehicle.trailer_brand'),
                data_get($orderMetadata, 'trailer_brand'),
            ]),
            'trailer_plate' => $trailerPlate,
        ];
    }

    private function loadFleetDriverName(int $fleetDriverId): ?string
    {
        if (! Schema::hasTable('fleet_drivers')) {
            return null;
        }

        $name = trim((string) (FleetDriver::query()->whereKey($fleetDriverId)->value('full_name') ?? ''));

        return $name !== '' ? $name : null;
    }

    private function loadLegacyDriverName(int $driverId): ?string
    {
        if (! Schema::hasTable('drivers')) {
            return null;
        }

        $driver = DB::table('drivers')
            ->select('first_name', 'last_name', 'patronymic')
            ->where('id', $driverId)
            ->first();

        if ($driver === null) {
            return null;
        }

        $name = trim(implode(' ', array_filter([
            $driver->last_name ?? null,
            $driver->first_name ?? null,
            $driver->patronymic ?? null,
        ])));

        return $name !== '' ? $name : null;
    }

    /**
     * @param  list<mixed>  $candidates
     */
    private function firstFilled(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (! is_string($candidate) && ! is_numeric($candidate)) {
                continue;
            }

            $trimmed = trim((string) $candidate);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }
}
