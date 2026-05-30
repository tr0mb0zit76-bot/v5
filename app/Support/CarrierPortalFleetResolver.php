<?php

namespace App\Support;

use App\Models\FleetDriver;
use App\Models\FleetVehicle;
use Illuminate\Support\Facades\Schema;

final class CarrierPortalFleetResolver
{
    /**
     * @param  array<string, mixed>  $identity
     */
    public function findVehicleId(int $contractorId, array $identity): ?int
    {
        if (! Schema::hasTable('fleet_vehicles')) {
            return null;
        }

        $tractorPlate = $this->normalizePlate($identity['tractor_plate'] ?? null);
        $trailerPlate = $this->normalizePlate($identity['trailer_plate'] ?? null);

        if ($tractorPlate !== null) {
            $existing = FleetVehicle::query()
                ->where('owner_contractor_id', $contractorId)
                ->where('tractor_plate', $tractorPlate)
                ->value('id');

            if ($existing !== null) {
                return (int) $existing;
            }
        }

        if ($trailerPlate !== null) {
            $existing = FleetVehicle::query()
                ->where('owner_contractor_id', $contractorId)
                ->where('trailer_plate', $trailerPlate)
                ->value('id');

            if ($existing !== null) {
                return (int) $existing;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $identity
     */
    public function findDriverId(int $contractorId, array $identity): ?int
    {
        if (! Schema::hasTable('fleet_drivers')) {
            return null;
        }

        $fullName = trim((string) ($identity['driver_full_name'] ?? ''));
        if ($fullName === '') {
            return null;
        }

        $phone = $this->nullableString($identity['driver_phone'] ?? null);
        $query = FleetDriver::query()
            ->where('carrier_contractor_id', $contractorId)
            ->where('full_name', $fullName);

        if ($phone !== null) {
            $existing = (clone $query)->where('phone', $phone)->value('id');
            if ($existing !== null) {
                return (int) $existing;
            }
        }

        $existing = $query->value('id');

        return $existing !== null ? (int) $existing : null;
    }

    /**
     * @param  array<string, mixed>  $identity
     */
    public function resolveVehicleId(int $contractorId, array $identity): ?int
    {
        if (! Schema::hasTable('fleet_vehicles')) {
            return null;
        }

        $tractorPlate = $this->normalizePlate($identity['tractor_plate'] ?? null);
        $trailerPlate = $this->normalizePlate($identity['trailer_plate'] ?? null);

        if ($tractorPlate === null && $trailerPlate === null) {
            return null;
        }

        $query = FleetVehicle::query()->where('owner_contractor_id', $contractorId);

        if ($tractorPlate !== null) {
            $existing = (clone $query)->where('tractor_plate', $tractorPlate)->first();
            if ($existing !== null) {
                $existing->forceFill(array_filter([
                    'trailer_plate' => $trailerPlate ?? $existing->trailer_plate,
                    'tractor_brand' => $this->nullableString($identity['tractor_brand'] ?? null) ?? $existing->tractor_brand,
                    'trailer_brand' => $this->nullableString($identity['trailer_brand'] ?? null) ?? $existing->trailer_brand,
                ], fn (mixed $value): bool => $value !== null))->save();

                return $existing->id;
            }
        }

        $vehicle = FleetVehicle::query()->create([
            'owner_contractor_id' => $contractorId,
            'tractor_plate' => $tractorPlate,
            'trailer_plate' => $trailerPlate,
            'tractor_brand' => $this->nullableString($identity['tractor_brand'] ?? null),
            'trailer_brand' => $this->nullableString($identity['trailer_brand'] ?? null),
        ]);

        return $vehicle->id;
    }

    /**
     * @param  array<string, mixed>  $identity
     */
    public function resolveDriverId(int $contractorId, array $identity): ?int
    {
        if (! Schema::hasTable('fleet_drivers')) {
            return null;
        }

        $fullName = trim((string) ($identity['driver_full_name'] ?? ''));
        if ($fullName === '') {
            return null;
        }

        $phone = $this->nullableString($identity['driver_phone'] ?? null);
        $licenseNumber = $this->nullableString($identity['driver_license'] ?? null);

        $query = FleetDriver::query()
            ->where('carrier_contractor_id', $contractorId)
            ->where('full_name', $fullName);

        if ($phone !== null) {
            $existing = (clone $query)->where('phone', $phone)->first();
            if ($existing !== null) {
                if ($licenseNumber !== null) {
                    $existing->forceFill(['license_number' => $licenseNumber])->save();
                }

                return $existing->id;
            }
        }

        $driver = FleetDriver::query()->create([
            'carrier_contractor_id' => $contractorId,
            'full_name' => $fullName,
            'phone' => $phone,
            'license_number' => $licenseNumber,
        ]);

        return $driver->id;
    }

    private function normalizePlate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $plate = mb_strtoupper(trim((string) $value));

        return $plate === '' ? null : $plate;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
