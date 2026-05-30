<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\Order;
use App\Models\OrderPortalInvite;
use App\Support\CarrierPortalFleetResolver;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderCarrierPortalSubmissionService
{
    public function __construct(
        private readonly OrderPortalInviteService $inviteService,
        private readonly OrderCarrierPortalDocumentService $portalDocumentService,
        private readonly OrderPortalInviteAccessService $inviteAccessService,
        private readonly CarrierPortalFleetResolver $fleetResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function submit(OrderPortalInvite $invite, array $validated): void
    {
        $order = Order::query()->findOrFail($invite->order_id);
        abort_unless($this->inviteAccessService->canSubmitFleetForm($order, $invite), 410, 'Ссылка недействительна или данные уже отправлены.');

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

            $fleetVehicleId = $this->fleetResolver->resolveVehicleId($contractor->id, $submission);
            $fleetDriverId = $this->fleetResolver->resolveDriverId($contractor->id, $submission);

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
