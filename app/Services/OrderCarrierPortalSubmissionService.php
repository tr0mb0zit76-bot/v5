<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\FleetDriver;
use App\Models\FleetVehicle;
use App\Models\Order;
use App\Models\OrderPortalInvite;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OrderCarrierPortalSubmissionService
{
    public function __construct(
        private readonly OrderPortalInviteService $inviteService,
        private readonly OrderCarrierPortalDocumentService $portalDocumentService,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function submit(OrderPortalInvite $invite, array $validated): void
    {
        abort_unless($invite->isOpenForSubmission(), 410, 'Ссылка недействительна или данные уже отправлены.');

        $missingDocuments = $this->portalDocumentService->missingRequiredSlotLabels($invite);
        if ($missingDocuments !== []) {
            throw ValidationException::withMessages([
                'documents' => 'Прикрепите обязательные документы: '.implode(', ', $missingDocuments),
            ]);
        }

        DB::transaction(function () use ($invite, $validated): void {
            $order = Order::query()->lockForUpdate()->findOrFail($invite->order_id);
            $contractor = Contractor::query()->findOrFail($invite->contractor_id);

            $submission = [
                'tractor_plate' => $this->normalizePlate(Arr::get($validated, 'tractor_plate')),
                'trailer_plate' => $this->normalizePlate(Arr::get($validated, 'trailer_plate')),
                'tractor_brand' => $this->nullableString(Arr::get($validated, 'tractor_brand')),
                'trailer_brand' => $this->nullableString(Arr::get($validated, 'trailer_brand')),
                'driver_full_name' => trim((string) Arr::get($validated, 'driver_full_name')),
                'driver_phone' => $this->nullableString(Arr::get($validated, 'driver_phone')),
                'driver_license' => $this->nullableString(Arr::get($validated, 'driver_license')),
                'comment' => $this->nullableString(Arr::get($validated, 'comment')),
                'submitted_at' => now()->toIso8601String(),
            ];

            $fleetVehicleId = $this->resolveFleetVehicleId($contractor->id, $submission);
            $fleetDriverId = $this->resolveFleetDriverId($contractor->id, $submission);

            $performers = is_array($order->performers) ? $order->performers : [];
            $performers = $this->applySubmissionToPerformers(
                $performers,
                $invite,
                $fleetVehicleId,
                $fleetDriverId,
                $submission,
            );

            $order->forceFill(['performers' => $performers])->save();

            $invite->forceFill([
                'used_at' => now(),
                'submitted_payload' => $submission,
            ])->save();
        });
    }

    /**
     * @param  list<array<string, mixed>>  $performers
     * @param  array<string, mixed>  $submission
     * @return list<array<string, mixed>>
     */
    private function applySubmissionToPerformers(
        array $performers,
        OrderPortalInvite $invite,
        ?int $fleetVehicleId,
        ?int $fleetDriverId,
        array $submission,
    ): array {
        $stage = $this->inviteService->normalizeStageIdentifier($invite->stage);
        $carrierSlot = (int) $invite->carrier_slot;

        return collect($performers)
            ->map(function (array $performer) use ($stage, $carrierSlot, $invite, $fleetVehicleId, $fleetDriverId, $submission): array {
                $performerStage = $this->inviteService->normalizeStageIdentifier((string) ($performer['stage'] ?? ''));
                if ($performerStage !== $stage) {
                    return $performer;
                }

                $carrierMode = ($performer['carrier_mode'] ?? 'single') === 'split' ? 'split' : 'single';

                if ($carrierMode === 'split' && is_array($performer['split_carriers'] ?? null)) {
                    $performer['split_carriers'] = collect($performer['split_carriers'])
                        ->map(function (array $slot) use ($carrierSlot, $invite, $fleetVehicleId, $fleetDriverId, $submission): array {
                            $slotNumber = (int) ($slot['slot'] ?? 1);
                            if ($slotNumber !== $carrierSlot) {
                                return $slot;
                            }

                            if ((int) ($slot['contractor_id'] ?? 0) !== (int) $invite->contractor_id) {
                                return $slot;
                            }

                            return $this->mergeFleetIntoTarget($slot, $fleetVehicleId, $fleetDriverId, $submission);
                        })
                        ->all();

                    return $performer;
                }

                if ((int) ($performer['contractor_id'] ?? 0) !== (int) $invite->contractor_id) {
                    return $performer;
                }

                return $this->mergeFleetIntoTarget($performer, $fleetVehicleId, $fleetDriverId, $submission);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $target
     * @param  array<string, mixed>  $submission
     * @return array<string, mixed>
     */
    private function mergeFleetIntoTarget(
        array $target,
        ?int $fleetVehicleId,
        ?int $fleetDriverId,
        array $submission,
    ): array {
        if ($fleetVehicleId !== null) {
            $target['fleet_vehicle_id'] = $fleetVehicleId;
        }

        if ($fleetDriverId !== null) {
            $target['fleet_driver_id'] = $fleetDriverId;
        }

        $target['carrier_portal_submission'] = $submission;

        return $target;
    }

    /**
     * @param  array<string, mixed>  $submission
     */
    private function resolveFleetVehicleId(int $contractorId, array $submission): ?int
    {
        if (! Schema::hasTable('fleet_vehicles')) {
            return null;
        }

        $tractorPlate = $submission['tractor_plate'] ?? null;
        $trailerPlate = $submission['trailer_plate'] ?? null;

        if ($tractorPlate === null && $trailerPlate === null) {
            return null;
        }

        $query = FleetVehicle::query()->where('owner_contractor_id', $contractorId);

        if ($tractorPlate !== null) {
            $existing = (clone $query)->where('tractor_plate', $tractorPlate)->first();
            if ($existing !== null) {
                $existing->forceFill(array_filter([
                    'trailer_plate' => $trailerPlate ?? $existing->trailer_plate,
                    'tractor_brand' => $submission['tractor_brand'] ?? $existing->tractor_brand,
                    'trailer_brand' => $submission['trailer_brand'] ?? $existing->trailer_brand,
                ], fn (mixed $value): bool => $value !== null))->save();

                return $existing->id;
            }
        }

        $vehicle = FleetVehicle::query()->create([
            'owner_contractor_id' => $contractorId,
            'tractor_plate' => $tractorPlate,
            'trailer_plate' => $trailerPlate,
            'tractor_brand' => $submission['tractor_brand'] ?? null,
            'trailer_brand' => $submission['trailer_brand'] ?? null,
        ]);

        return $vehicle->id;
    }

    /**
     * @param  array<string, mixed>  $submission
     */
    private function resolveFleetDriverId(int $contractorId, array $submission): ?int
    {
        if (! Schema::hasTable('fleet_drivers')) {
            return null;
        }

        $fullName = trim((string) ($submission['driver_full_name'] ?? ''));
        if ($fullName === '') {
            return null;
        }

        $phone = $submission['driver_phone'] ?? null;

        $query = FleetDriver::query()
            ->where('carrier_contractor_id', $contractorId)
            ->where('full_name', $fullName);

        if ($phone !== null) {
            $existing = (clone $query)->where('phone', $phone)->first();
            if ($existing !== null) {
                if (($submission['driver_license'] ?? null) !== null) {
                    $existing->forceFill(['license_number' => $submission['driver_license']])->save();
                }

                return $existing->id;
            }
        }

        $driver = FleetDriver::query()->create([
            'carrier_contractor_id' => $contractorId,
            'full_name' => $fullName,
            'phone' => $phone,
            'license_number' => $submission['driver_license'] ?? null,
        ]);

        return $driver->id;
    }

    private function normalizePlate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $plate = mb_strtoupper(trim((string) $value));
        if ($plate === '') {
            return null;
        }

        return $plate;
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
