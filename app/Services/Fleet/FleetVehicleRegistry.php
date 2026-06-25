<?php

namespace App\Services\Fleet;

use App\Models\FleetVehicle;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class FleetVehicleRegistry
{
    /**
     * @param  array{
     *     tractor_brand?: string|null,
     *     trailer_brand?: string|null,
     *     tractor_plate?: string|null,
     *     trailer_plate?: string|null,
     *     notes?: string|null
     * }  $attributes
     */
    public function register(int $ownerContractorId, array $attributes): FleetVehicle
    {
        if (! Schema::hasTable('fleet_vehicles')) {
            throw new RuntimeException('Раздел «Авто» недоступен.');
        }

        $tractorPlate = $this->normalizePlate($attributes['tractor_plate'] ?? null);
        $trailerPlate = $this->normalizePlate($attributes['trailer_plate'] ?? null);
        $tractorBrand = $this->nullableString($attributes['tractor_brand'] ?? null);
        $trailerBrand = $this->nullableString($attributes['trailer_brand'] ?? null);
        $notes = $this->nullableString($attributes['notes'] ?? null);

        if ($tractorPlate === null && $trailerPlate === null && $tractorBrand === null && $trailerBrand === null) {
            throw ValidationException::withMessages([
                'tractor_plate' => 'Укажите хотя бы госномер или марку тягача/прицепа.',
            ]);
        }

        $existing = $this->findExisting($ownerContractorId, $tractorPlate, $trailerPlate);
        if ($existing !== null) {
            $existing->forceFill(array_filter([
                'tractor_plate' => $tractorPlate ?? $existing->tractor_plate,
                'trailer_plate' => $trailerPlate ?? $existing->trailer_plate,
                'tractor_brand' => $tractorBrand ?? $existing->tractor_brand,
                'trailer_brand' => $trailerBrand ?? $existing->trailer_brand,
                'notes' => $notes ?? $existing->notes,
            ], fn (mixed $value): bool => $value !== null))->save();

            return $existing->fresh() ?? $existing;
        }

        return FleetVehicle::query()->create([
            'owner_contractor_id' => $ownerContractorId,
            'tractor_plate' => $tractorPlate,
            'trailer_plate' => $trailerPlate,
            'tractor_brand' => $tractorBrand,
            'trailer_brand' => $trailerBrand,
            'notes' => $notes,
        ]);
    }

    public function normalizePlate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $plate = mb_strtoupper(trim((string) $value));

        return $plate === '' ? null : $plate;
    }

    public function findExisting(int $ownerContractorId, ?string $tractorPlate, ?string $trailerPlate): ?FleetVehicle
    {
        if (! Schema::hasTable('fleet_vehicles')) {
            return null;
        }

        if ($tractorPlate !== null) {
            $existing = FleetVehicle::query()
                ->where('owner_contractor_id', $ownerContractorId)
                ->where('tractor_plate', $tractorPlate)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        if ($trailerPlate !== null) {
            return FleetVehicle::query()
                ->where('owner_contractor_id', $ownerContractorId)
                ->where('trailer_plate', $trailerPlate)
                ->first();
        }

        return null;
    }

    public function assertUniqueForUpdate(
        FleetVehicle $vehicle,
        int $ownerContractorId,
        ?string $tractorPlate,
        ?string $trailerPlate,
    ): void {
        if ($tractorPlate !== null) {
            $duplicate = FleetVehicle::query()
                ->where('owner_contractor_id', $ownerContractorId)
                ->where('tractor_plate', $tractorPlate)
                ->where('id', '!=', $vehicle->id)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'tractor_plate' => 'ТС с таким госномером тягача уже есть у этого владельца.',
                ]);
            }
        }

        if ($trailerPlate !== null) {
            $duplicate = FleetVehicle::query()
                ->where('owner_contractor_id', $ownerContractorId)
                ->where('trailer_plate', $trailerPlate)
                ->where('id', '!=', $vehicle->id)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'trailer_plate' => 'ТС с таким госномером прицепа уже есть у этого владельца.',
                ]);
            }
        }
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
