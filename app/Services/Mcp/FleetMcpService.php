<?php

namespace App\Services\Mcp;

use App\Models\FleetDriver;
use App\Models\FleetVehicle;
use App\Models\User;
use App\Services\Fleet\FleetVehicleRegistry;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class FleetMcpService
{
    public function __construct(
        private readonly McpAccessGate $access,
        private readonly FleetVehicleRegistry $fleetVehicleRegistry,
    ) {}

    /**
     * @param  array{
     *     carrier_contractor_id: int,
     *     full_name: string,
     *     passport_series?: string|null,
     *     passport_number?: string|null,
     *     passport_issued_by?: string|null,
     *     passport_issued_at?: string|null,
     *     phone?: string|null,
     *     license_number?: string|null,
     *     license_categories?: string|null,
     *     notes?: string|null
     * }  $payload
     * @return array{driver: array<string, mixed>, show_path: string}
     */
    public function createDriver(User $user, array $payload): array
    {
        $this->access->requireDriversArea($user);

        if (! Schema::hasTable('fleet_drivers')) {
            throw new RuntimeException('Раздел «Водители» недоступен.');
        }

        $validated = Validator::make($payload, [
            'carrier_contractor_id' => ['required', 'integer', 'exists:contractors,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'passport_series' => ['nullable', 'string', 'max:16'],
            'passport_number' => ['nullable', 'string', 'max:32'],
            'passport_issued_by' => ['nullable', 'string', 'max:500'],
            'passport_issued_at' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:50'],
            'license_number' => ['nullable', 'string', 'max:64'],
            'license_categories' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        $driver = FleetDriver::query()->create($validated);
        $driver->load('carrier:id,name,inn');

        return [
            'driver' => $this->summarizeDriver($driver),
            'show_path' => route('fleet.drivers.show', $driver),
        ];
    }

    /**
     * @param  array{
     *     owner_contractor_id: int,
     *     tractor_brand?: string|null,
     *     trailer_brand?: string|null,
     *     tractor_plate?: string|null,
     *     trailer_plate?: string|null,
     *     notes?: string|null
     * }  $payload
     * @return array{vehicle: array<string, mixed>, show_path: string}
     */
    public function createVehicle(User $user, array $payload): array
    {
        $this->access->requireDriversArea($user);

        if (! Schema::hasTable('fleet_vehicles')) {
            throw new RuntimeException('Раздел «Авто» недоступен.');
        }

        $validated = Validator::make($payload, [
            'owner_contractor_id' => ['required', 'integer', 'exists:contractors,id'],
            'tractor_brand' => ['nullable', 'string', 'max:120'],
            'trailer_brand' => ['nullable', 'string', 'max:120'],
            'tractor_plate' => ['nullable', 'string', 'max:32'],
            'trailer_plate' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        $vehicle = $this->fleetVehicleRegistry->register((int) $validated['owner_contractor_id'], $validated);
        $vehicle->load('owner:id,name,inn');

        return [
            'vehicle' => $this->summarizeVehicle($vehicle),
            'show_path' => route('fleet.vehicles.show', $vehicle),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summarizeDriver(FleetDriver $driver): array
    {
        return [
            'id' => $driver->id,
            'carrier_contractor_id' => $driver->carrier_contractor_id,
            'carrier_name' => $driver->carrier?->name,
            'carrier_inn' => $driver->carrier?->inn,
            'full_name' => $driver->full_name,
            'phone' => $driver->phone,
            'license_number' => $driver->license_number,
            'license_categories' => $driver->license_categories,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summarizeVehicle(FleetVehicle $vehicle): array
    {
        return [
            'id' => $vehicle->id,
            'owner_contractor_id' => $vehicle->owner_contractor_id,
            'owner_name' => $vehicle->owner?->name,
            'owner_inn' => $vehicle->owner?->inn,
            'tractor_brand' => $vehicle->tractor_brand,
            'trailer_brand' => $vehicle->trailer_brand,
            'tractor_plate' => $vehicle->tractor_plate,
            'trailer_plate' => $vehicle->trailer_plate,
        ];
    }
}
