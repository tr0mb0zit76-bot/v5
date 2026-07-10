<?php

namespace App\Services\Orders\Wizard;

use App\Models\Contractor;
use App\Models\FinancialTerm;
use App\Models\FleetDriver;
use App\Models\FleetVehicle;
use App\Models\LegContractorAssignment;
use App\Models\Order;
use App\Support\CargoPerformerAllocationNormalizer;
use App\Support\OwnFleetCatalog;
use App\Support\PerformerRouteActualDates;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class OrderWizardPerformersPayloadBuilder
{
    /**
     * Исполнители для мастера: плечи заказа; перевозчик — из назначения на плече, при отсутствии — из snapshot `financial_terms.contractors_costs`.
     *
     * @return list<array{stage: string|null, contractor_id: int|null, contractor_name: string|null}>
     */
    public function build(Order $order, ?FinancialTerm $financialTerm): array
    {
        $costRows = $financialTerm?->contractors_costs ?? [];
        if (! is_array($costRows)) {
            $costRows = [];
        }
        $savedPerformers = is_array($order->performers) ? $order->performers : [];
        $savedPerformersByStage = collect($savedPerformers)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->keyBy(fn (array $row): string => CargoPerformerAllocationNormalizer::normalizeStageIdentifier((string) ($row['stage'] ?? 'leg_1')));

        $costsByNormalizedStage = collect($costRows)
            ->keyBy(fn (array $cost): string => $this->contractorCostSnapshotKey($cost));

        if (Schema::hasTable('order_legs')) {
            if (Schema::hasTable('leg_contractor_assignments')) {
                $order->loadMissing(['legs.contractorAssignments', 'legs.contractorAssignment']);
            } else {
                $order->loadMissing(['legs']);
            }
        }

        $fromLegs = $order->relationLoaded('legs')
            ? $order->legs
                ->sortBy('sequence')
                ->values()
                ->map(function ($leg, int $index) use ($costsByNormalizedStage, $order): array {
                    $normalized = CargoPerformerAllocationNormalizer::normalizeStageIdentifier((string) ($leg->description ?? 'leg_1'));
                    $contractorId = null;
                    $metadataPerformer = is_array($leg->metadata ?? null)
                        ? (is_array($leg->metadata['performer'] ?? null) ? $leg->metadata['performer'] : [])
                        : [];
                    $carrierMode = (string) ($metadataPerformer['carrier_mode'] ?? 'single');
                    $splitCarriers = is_array($metadataPerformer['split_carriers'] ?? null)
                        ? $metadataPerformer['split_carriers']
                        : [];

                    if (Schema::hasTable('leg_contractor_assignments') && $leg->relationLoaded('contractorAssignments')) {
                        $assignments = $leg->contractorAssignments;
                        if ($assignments->count() > 1 || $carrierMode === 'split') {
                            $carrierMode = 'split';
                            $splitCarriers = $this->splitCarriersFromAssignmentsAndMetadata($assignments, $metadataPerformer);
                        } elseif ($assignments->count() === 1) {
                            $contractorId = $assignments->first()?->contractor_id;
                        }
                    } elseif (Schema::hasTable('leg_contractor_assignments')) {
                        $contractorId = $leg->contractorAssignment?->contractor_id;
                    }

                    if ($contractorId === null && $carrierMode !== 'split') {
                        $fromCost = $costsByNormalizedStage->get($this->contractorCostSnapshotKey([
                            'stage' => $normalized,
                            'carrier_slot' => null,
                        ]));
                        $contractorId = is_array($fromCost) ? ($fromCost['contractor_id'] ?? null) : null;
                    }

                    if ($contractorId === null && $index === 0 && $order->carrier_id !== null && $carrierMode !== 'split') {
                        $contractorId = $order->carrier_id;
                    }

                    return [
                        'stage' => $normalized,
                        'carrier_mode' => $carrierMode,
                        'contractor_id' => $carrierMode === 'split' ? null : ($contractorId !== null ? (int) $contractorId : null),
                        'contractor_name' => isset($metadataPerformer['contractor_name']) ? (string) $metadataPerformer['contractor_name'] : null,
                        'fleet_vehicle_id' => isset($metadataPerformer['fleet_vehicle_id']) && $metadataPerformer['fleet_vehicle_id'] !== null
                            ? (int) $metadataPerformer['fleet_vehicle_id']
                            : null,
                        'fleet_driver_id' => isset($metadataPerformer['fleet_driver_id']) && $metadataPerformer['fleet_driver_id'] !== null
                            ? (int) $metadataPerformer['fleet_driver_id']
                            : null,
                        'carrier_portal_submission' => is_array($metadataPerformer['carrier_portal_submission'] ?? null)
                            ? $metadataPerformer['carrier_portal_submission']
                            : null,
                        'split_carriers' => $splitCarriers,
                    ];
                })
                ->all()
            : [];

        if ($fromLegs !== []) {
            return $this->performersPayloadWithFleetLabels(
                $this->performersPayloadWithContractorLabels(
                    $this->mergeSavedPerformersIntoLegPayload($fromLegs, $savedPerformers)
                ),
            );
        }

        if ($costRows !== []) {
            $fromCosts = collect($costRows)
                ->map(function (array $cost) use ($savedPerformersByStage): array {
                    $stage = (string) ($cost['stage'] ?? 'leg_1');
                    $saved = $savedPerformersByStage->get(CargoPerformerAllocationNormalizer::normalizeStageIdentifier($stage));

                    return [
                        'stage' => CargoPerformerAllocationNormalizer::normalizeStageIdentifier($stage),
                        'contractor_id' => isset($cost['contractor_id']) && $cost['contractor_id'] !== null ? (int) $cost['contractor_id'] : null,
                        'fleet_vehicle_id' => isset($saved['fleet_vehicle_id']) && $saved['fleet_vehicle_id'] !== null ? (int) $saved['fleet_vehicle_id'] : null,
                        'fleet_driver_id' => isset($saved['fleet_driver_id']) && $saved['fleet_driver_id'] !== null ? (int) $saved['fleet_driver_id'] : null,
                        'carrier_portal_submission' => is_array($saved['carrier_portal_submission'] ?? null)
                            ? $saved['carrier_portal_submission']
                            : null,
                    ];
                })
                ->values()
                ->all();

            return $this->performersPayloadWithFleetLabels(
                $this->performersPayloadWithContractorLabels($fromCosts),
            );
        }

        if ($order->carrier_id !== null) {
            return $this->performersPayloadWithFleetLabels(
                $this->performersPayloadWithContractorLabels([
                    [
                        'stage' => 'leg_1',
                        'contractor_id' => (int) $order->carrier_id,
                    ],
                ]),
            );
        }

        return [];
    }

    /**
     * @param  list<array<string, mixed>>  $serializedPerformers
     * @return list<array<string, mixed>>
     */
    public function mergeFromWizardState(array $serializedPerformers, mixed $wizardPerformers): array
    {
        if (! is_array($wizardPerformers) || $wizardPerformers === []) {
            return $serializedPerformers;
        }

        $wizardByStage = collect($wizardPerformers)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->keyBy(fn (array $row): string => CargoPerformerAllocationNormalizer::normalizeStageIdentifier((string) ($row['stage'] ?? 'leg_1')));

        if ($serializedPerformers === []) {
            return $this->performersPayloadWithContractorLabels(
                $wizardByStage->values()->map(fn (array $row): array => $this->normalizePerformerRowFromWizardState($row))->all()
            );
        }

        return $this->performersPayloadWithContractorLabels(
            collect($serializedPerformers)
                ->map(function (array $serialized) use ($wizardByStage): array {
                    $stageKey = CargoPerformerAllocationNormalizer::normalizeStageIdentifier((string) ($serialized['stage'] ?? 'leg_1'));
                    $wizardRow = $wizardByStage->get($stageKey);

                    if (! is_array($wizardRow)) {
                        return $serialized;
                    }

                    return $this->mergePerformerRow($serialized, $this->normalizePerformerRowFromWizardState($wizardRow));
                })
                ->values()
                ->all()
        );
    }

    /**
     * @param  list<array<string, mixed>>  $performers
     * @return list<array<string, mixed>>
     */
    public function withFleetLabels(array $performers): array
    {
        return $this->performersPayloadWithFleetLabels($performers);
    }

    public function contractorCostSnapshotKey(array $row): string
    {
        $stage = CargoPerformerAllocationNormalizer::normalizeStageIdentifier((string) ($row['stage'] ?? 'leg_1'));
        $slot = isset($row['carrier_slot']) && $row['carrier_slot'] !== null && $row['carrier_slot'] !== ''
            ? (int) $row['carrier_slot']
            : 0;

        return "{$stage}#{$slot}";
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizePerformerRowFromWizardState(array $row): array
    {
        $normalized = [
            'stage' => CargoPerformerAllocationNormalizer::normalizeStageIdentifier(isset($row['stage']) ? (string) $row['stage'] : null),
            'carrier_mode' => ($row['carrier_mode'] ?? 'single') === 'split' ? 'split' : 'single',
            'contractor_id' => isset($row['contractor_id']) && $row['contractor_id'] !== null && $row['contractor_id'] !== ''
                ? (int) $row['contractor_id']
                : null,
            'contractor_name' => isset($row['contractor_name']) ? (string) $row['contractor_name'] : null,
            'fleet_vehicle_id' => isset($row['fleet_vehicle_id']) && $row['fleet_vehicle_id'] !== null && $row['fleet_vehicle_id'] !== ''
                ? (int) $row['fleet_vehicle_id']
                : null,
            'fleet_driver_id' => isset($row['fleet_driver_id']) && $row['fleet_driver_id'] !== null && $row['fleet_driver_id'] !== ''
                ? (int) $row['fleet_driver_id']
                : null,
            'execution_mode' => OwnFleetCatalog::isOwnFleetExecutionMode(isset($row['execution_mode']) ? (string) $row['execution_mode'] : null)
                ? OwnFleetCatalog::EXECUTION_MODE_OWN_FLEET
                : null,
            'fleet_trip_id' => isset($row['fleet_trip_id']) && $row['fleet_trip_id'] !== null && $row['fleet_trip_id'] !== ''
                ? (int) $row['fleet_trip_id']
                : null,
            'loading_actual' => PerformerRouteActualDates::normalizeDate($row['loading_actual'] ?? null),
            'unloading_actual' => PerformerRouteActualDates::normalizeDate($row['unloading_actual'] ?? null),
            'carrier_portal_submission' => is_array($row['carrier_portal_submission'] ?? null)
                ? $row['carrier_portal_submission']
                : null,
            'loading_special_conditions' => filled($row['loading_special_conditions'] ?? null)
                ? trim((string) $row['loading_special_conditions'])
                : null,
            'unloading_special_conditions' => filled($row['unloading_special_conditions'] ?? null)
                ? trim((string) $row['unloading_special_conditions'])
                : null,
            'split_carriers' => [],
        ];

        if ($normalized['carrier_mode'] === 'split' && is_array($row['split_carriers'] ?? null)) {
            $normalized['split_carriers'] = collect($row['split_carriers'])
                ->filter(fn (mixed $slot): bool => is_array($slot))
                ->values()
                ->map(function (array $slot, int $index): array {
                    return [
                        'slot' => (int) ($slot['slot'] ?? ($index + 1)),
                        'contractor_id' => isset($slot['contractor_id']) && $slot['contractor_id'] !== null && $slot['contractor_id'] !== ''
                            ? (int) $slot['contractor_id']
                            : null,
                        'contractor_name' => isset($slot['contractor_name']) ? (string) $slot['contractor_name'] : null,
                        'fleet_vehicle_id' => isset($slot['fleet_vehicle_id']) && $slot['fleet_vehicle_id'] !== null && $slot['fleet_vehicle_id'] !== ''
                            ? (int) $slot['fleet_vehicle_id']
                            : null,
                        'fleet_driver_id' => isset($slot['fleet_driver_id']) && $slot['fleet_driver_id'] !== null && $slot['fleet_driver_id'] !== ''
                            ? (int) $slot['fleet_driver_id']
                            : null,
                        'execution_mode' => OwnFleetCatalog::isOwnFleetExecutionMode(isset($slot['execution_mode']) ? (string) $slot['execution_mode'] : null)
                            ? OwnFleetCatalog::EXECUTION_MODE_OWN_FLEET
                            : null,
                        'fleet_trip_id' => isset($slot['fleet_trip_id']) && $slot['fleet_trip_id'] !== null && $slot['fleet_trip_id'] !== ''
                            ? (int) $slot['fleet_trip_id']
                            : null,
                        'carrier_portal_submission' => is_array($slot['carrier_portal_submission'] ?? null)
                            ? $slot['carrier_portal_submission']
                            : null,
                        'loading_actual' => PerformerRouteActualDates::normalizeDate($slot['loading_actual'] ?? null),
                        'unloading_actual' => PerformerRouteActualDates::normalizeDate($slot['unloading_actual'] ?? null),
                    ];
                })
                ->all();
            $normalized['loading_actual'] = null;
            $normalized['unloading_actual'] = null;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $wizard
     * @return array<string, mixed>
     */
    private function mergePerformerRow(array $base, array $wizard): array
    {
        if (($wizard['carrier_mode'] ?? 'single') === 'split') {
            $baseSlots = is_array($base['split_carriers'] ?? null) ? $base['split_carriers'] : [];
            $wizardSlots = is_array($wizard['split_carriers'] ?? null) ? $wizard['split_carriers'] : [];

            return [
                ...$base,
                'carrier_mode' => 'split',
                'split_carriers' => $wizardSlots === [] && $baseSlots !== []
                    ? $baseSlots
                    : $this->mergeSplitCarriersFromWizardState($baseSlots, $wizardSlots),
                'contractor_id' => null,
                'contractor_name' => null,
                'fleet_vehicle_id' => null,
                'fleet_driver_id' => null,
                'loading_actual' => null,
                'unloading_actual' => null,
                'loading_special_conditions' => $wizard['loading_special_conditions'] ?? $base['loading_special_conditions'] ?? null,
                'unloading_special_conditions' => $wizard['unloading_special_conditions'] ?? $base['unloading_special_conditions'] ?? null,
            ];
        }

        return [
            ...$base,
            'carrier_mode' => 'single',
            'split_carriers' => [],
            'contractor_id' => $wizard['contractor_id'] ?? $base['contractor_id'] ?? null,
            'contractor_name' => $wizard['contractor_name'] ?? $base['contractor_name'] ?? null,
            'fleet_vehicle_id' => $wizard['fleet_vehicle_id'] ?? $base['fleet_vehicle_id'] ?? null,
            'fleet_driver_id' => $wizard['fleet_driver_id'] ?? $base['fleet_driver_id'] ?? null,
            'execution_mode' => $wizard['execution_mode'] ?? $base['execution_mode'] ?? null,
            'fleet_trip_id' => $wizard['fleet_trip_id'] ?? $base['fleet_trip_id'] ?? null,
            'carrier_portal_submission' => $wizard['carrier_portal_submission'] ?? $base['carrier_portal_submission'] ?? null,
            'loading_actual' => $wizard['loading_actual'] ?? $base['loading_actual'] ?? null,
            'unloading_actual' => $wizard['unloading_actual'] ?? $base['unloading_actual'] ?? null,
            'loading_special_conditions' => $wizard['loading_special_conditions'] ?? $base['loading_special_conditions'] ?? null,
            'unloading_special_conditions' => $wizard['unloading_special_conditions'] ?? $base['unloading_special_conditions'] ?? null,
        ];
    }

    /**
     * @param  Collection<int, LegContractorAssignment>  $assignments
     * @param  array<string, mixed>  $metadataPerformer
     * @return list<array<string, mixed>>
     */
    private function splitCarriersFromAssignmentsAndMetadata(Collection $assignments, array $metadataPerformer): array
    {
        $metadataSlots = collect(is_array($metadataPerformer['split_carriers'] ?? null) ? $metadataPerformer['split_carriers'] : [])
            ->filter(fn (mixed $row): bool => is_array($row))
            ->keyBy(fn (array $row): int => (int) ($row['slot'] ?? 1));

        if ($assignments->isNotEmpty()) {
            return $assignments
                ->sortBy('carrier_slot')
                ->map(function ($assignment) use ($metadataSlots): array {
                    $slot = (int) ($assignment->carrier_slot ?? 1);
                    $meta = $metadataSlots->get($slot, []);

                    return [
                        'slot' => $slot,
                        'contractor_id' => $assignment->contractor_id !== null ? (int) $assignment->contractor_id : null,
                        'contractor_name' => filled($meta['contractor_name'] ?? null) ? (string) $meta['contractor_name'] : null,
                        'fleet_vehicle_id' => isset($meta['fleet_vehicle_id']) && $meta['fleet_vehicle_id'] !== null && $meta['fleet_vehicle_id'] !== ''
                            ? (int) $meta['fleet_vehicle_id']
                            : null,
                        'fleet_driver_id' => isset($meta['fleet_driver_id']) && $meta['fleet_driver_id'] !== null && $meta['fleet_driver_id'] !== ''
                            ? (int) $meta['fleet_driver_id']
                            : null,
                    ];
                })
                ->values()
                ->all();
        }

        if ($metadataSlots->isNotEmpty()) {
            return $metadataSlots
                ->sortKeys()
                ->map(fn (array $meta, int $slot): array => [
                    'slot' => $slot,
                    'contractor_id' => isset($meta['contractor_id']) && $meta['contractor_id'] !== null && $meta['contractor_id'] !== ''
                        ? (int) $meta['contractor_id']
                        : null,
                    'contractor_name' => filled($meta['contractor_name'] ?? null) ? (string) $meta['contractor_name'] : null,
                    'fleet_vehicle_id' => isset($meta['fleet_vehicle_id']) && $meta['fleet_vehicle_id'] !== null && $meta['fleet_vehicle_id'] !== ''
                        ? (int) $meta['fleet_vehicle_id']
                        : null,
                    'fleet_driver_id' => isset($meta['fleet_driver_id']) && $meta['fleet_driver_id'] !== null && $meta['fleet_driver_id'] !== ''
                        ? (int) $meta['fleet_driver_id']
                        : null,
                ])
                ->values()
                ->all();
        }

        return [];
    }

    /**
     * Слоты из wizard_state не должны затирать перевозчиков, уже восстановленных из БД.
     *
     * @param  list<array<string, mixed>>  $baseSlots
     * @param  list<array<string, mixed>>  $wizardSlots
     * @return list<array<string, mixed>>
     */
    private function mergeSplitCarriersFromWizardState(array $baseSlots, array $wizardSlots): array
    {
        if ($wizardSlots === []) {
            return $baseSlots;
        }

        $baseBySlot = collect($baseSlots)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->keyBy(fn (array $row): int => (int) ($row['slot'] ?? 1));

        return collect($wizardSlots)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->values()
            ->map(function (array $wizardSlot) use ($baseBySlot): array {
                $slot = (int) ($wizardSlot['slot'] ?? 1);
                $baseSlot = $baseBySlot->get($slot);
                $wizardContractorId = isset($wizardSlot['contractor_id']) && $wizardSlot['contractor_id'] !== null && $wizardSlot['contractor_id'] !== ''
                    ? (int) $wizardSlot['contractor_id']
                    : null;
                $baseContractorId = is_array($baseSlot) && isset($baseSlot['contractor_id']) && $baseSlot['contractor_id'] !== null && $baseSlot['contractor_id'] !== ''
                    ? (int) $baseSlot['contractor_id']
                    : null;

                return [
                    'slot' => $slot,
                    'contractor_id' => $wizardContractorId ?? $baseContractorId,
                    'contractor_name' => filled($wizardSlot['contractor_name'] ?? null)
                        ? (string) $wizardSlot['contractor_name']
                        : (is_array($baseSlot) ? ($baseSlot['contractor_name'] ?? null) : null),
                    'fleet_vehicle_id' => isset($wizardSlot['fleet_vehicle_id']) && $wizardSlot['fleet_vehicle_id'] !== null && $wizardSlot['fleet_vehicle_id'] !== ''
                        ? (int) $wizardSlot['fleet_vehicle_id']
                        : (is_array($baseSlot) && isset($baseSlot['fleet_vehicle_id']) ? $baseSlot['fleet_vehicle_id'] : null),
                    'fleet_driver_id' => isset($wizardSlot['fleet_driver_id']) && $wizardSlot['fleet_driver_id'] !== null && $wizardSlot['fleet_driver_id'] !== ''
                        ? (int) $wizardSlot['fleet_driver_id']
                        : (is_array($baseSlot) && isset($baseSlot['fleet_driver_id']) ? $baseSlot['fleet_driver_id'] : null),
                    'carrier_portal_submission' => is_array($wizardSlot['carrier_portal_submission'] ?? null)
                        ? $wizardSlot['carrier_portal_submission']
                        : (is_array($baseSlot['carrier_portal_submission'] ?? null) ? $baseSlot['carrier_portal_submission'] : null),
                    'loading_actual' => PerformerRouteActualDates::normalizeDate(
                        $wizardSlot['loading_actual'] ?? (is_array($baseSlot) ? ($baseSlot['loading_actual'] ?? null) : null),
                    ),
                    'unloading_actual' => PerformerRouteActualDates::normalizeDate(
                        $wizardSlot['unloading_actual'] ?? (is_array($baseSlot) ? ($baseSlot['unloading_actual'] ?? null) : null),
                    ),
                ];
            })
            ->all();
    }

    /**
     * Подпись перевозчика в мастере: поле поиска не должно пустеть, если id есть, а контрагент не попал в укороченный список props.
     *
     * @param  list<array<string, mixed>>  $performers
     * @return list<array<string, mixed>>
     */
    private function performersPayloadWithContractorLabels(array $performers): array
    {
        if ($performers === []) {
            return [];
        }

        $ids = collect($performers)
            ->flatMap(function (array $performer): array {
                if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                    return collect($performer['split_carriers'])
                        ->filter(fn (mixed $slot): bool => is_array($slot))
                        ->map(fn (array $slot): ?int => isset($slot['contractor_id']) && $slot['contractor_id'] !== null
                            ? (int) $slot['contractor_id']
                            : null)
                        ->all();
                }

                $id = $performer['contractor_id'] ?? null;

                return $id !== null && $id !== '' ? [(int) $id] : [];
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect($performers)
                ->map(fn (array $p): array => [...$p, 'contractor_name' => null])
                ->all();
        }

        $names = Contractor::query()->whereIn('id', $ids)->pluck('name', 'id');

        return collect($performers)
            ->map(function (array $p) use ($names): array {
                if (($p['carrier_mode'] ?? 'single') === 'split' && is_array($p['split_carriers'] ?? null)) {
                    $p['split_carriers'] = collect($p['split_carriers'])
                        ->map(function (array $slot) use ($names): array {
                            $idInt = isset($slot['contractor_id']) && $slot['contractor_id'] !== null
                                ? (int) $slot['contractor_id']
                                : null;
                            $label = $idInt !== null ? $names->get($idInt) : null;
                            $slotName = trim((string) ($slot['contractor_name'] ?? ''));

                            return [
                                ...$slot,
                                'contractor_name' => $slotName !== ''
                                    ? $slotName
                                    : ($label !== null && $label !== '' ? (string) $label : null),
                            ];
                        })
                        ->all();

                    return $p;
                }

                $id = $p['contractor_id'] ?? null;
                $idInt = $id !== null && $id !== '' ? (int) $id : null;
                $label = $idInt !== null ? $names->get($idInt) : null;
                $existingName = trim((string) ($p['contractor_name'] ?? ''));

                return [
                    ...$p,
                    'contractor_name' => $existingName !== ''
                        ? $existingName
                        : ($label !== null && $label !== '' ? (string) $label : null),
                ];
            })
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $performers
     * @return list<array<string, mixed>>
     */
    private function performersPayloadWithFleetLabels(array $performers): array
    {
        if ($performers === []) {
            return [];
        }

        $vehicleIds = [];
        $driverIds = [];

        foreach ($performers as $performer) {
            if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                foreach ($performer['split_carriers'] as $slot) {
                    if (! is_array($slot)) {
                        continue;
                    }

                    if (isset($slot['fleet_vehicle_id']) && $slot['fleet_vehicle_id'] !== null) {
                        $vehicleIds[(int) $slot['fleet_vehicle_id']] = true;
                    }

                    if (isset($slot['fleet_driver_id']) && $slot['fleet_driver_id'] !== null) {
                        $driverIds[(int) $slot['fleet_driver_id']] = true;
                    }
                }

                continue;
            }

            if (isset($performer['fleet_vehicle_id']) && $performer['fleet_vehicle_id'] !== null) {
                $vehicleIds[(int) $performer['fleet_vehicle_id']] = true;
            }

            if (isset($performer['fleet_driver_id']) && $performer['fleet_driver_id'] !== null) {
                $driverIds[(int) $performer['fleet_driver_id']] = true;
            }
        }

        $vehicleLabels = $this->fleetVehicleLabels(array_keys($vehicleIds));
        $driverLabels = $this->fleetDriverLabels(array_keys($driverIds));

        return collect($performers)
            ->map(function (array $performer) use ($vehicleLabels, $driverLabels): array {
                if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                    $performer['split_carriers'] = collect($performer['split_carriers'])
                        ->map(function (array $slot) use ($vehicleLabels, $driverLabels): array {
                            $vehicleId = isset($slot['fleet_vehicle_id']) && $slot['fleet_vehicle_id'] !== null
                                ? (int) $slot['fleet_vehicle_id']
                                : null;
                            $driverId = isset($slot['fleet_driver_id']) && $slot['fleet_driver_id'] !== null
                                ? (int) $slot['fleet_driver_id']
                                : null;

                            return [
                                ...$slot,
                                'fleet_vehicle_label' => $vehicleId !== null ? ($vehicleLabels[$vehicleId] ?? null) : null,
                                'fleet_driver_label' => $driverId !== null ? ($driverLabels[$driverId] ?? null) : null,
                            ];
                        })
                        ->all();

                    return $performer;
                }

                $vehicleId = isset($performer['fleet_vehicle_id']) && $performer['fleet_vehicle_id'] !== null
                    ? (int) $performer['fleet_vehicle_id']
                    : null;
                $driverId = isset($performer['fleet_driver_id']) && $performer['fleet_driver_id'] !== null
                    ? (int) $performer['fleet_driver_id']
                    : null;

                return [
                    ...$performer,
                    'fleet_vehicle_label' => $vehicleId !== null ? ($vehicleLabels[$vehicleId] ?? null) : null,
                    'fleet_driver_label' => $driverId !== null ? ($driverLabels[$driverId] ?? null) : null,
                ];
            })
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function fleetVehicleLabels(array $ids): array
    {
        if ($ids === [] || ! Schema::hasTable('fleet_vehicles')) {
            return [];
        }

        return FleetVehicle::query()
            ->whereIn('id', $ids)
            ->get(['id', 'tractor_plate', 'trailer_plate', 'tractor_brand'])
            ->mapWithKeys(function (FleetVehicle $vehicle): array {
                $parts = array_filter([
                    $vehicle->tractor_plate,
                    $vehicle->trailer_plate,
                    $vehicle->tractor_brand,
                ]);

                $label = $parts !== [] ? implode(' · ', $parts) : 'ТС #'.$vehicle->id;

                return [$vehicle->id => $label];
            })
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function fleetDriverLabels(array $ids): array
    {
        if ($ids === [] || ! Schema::hasTable('fleet_drivers')) {
            return [];
        }

        return FleetDriver::query()
            ->whereIn('id', $ids)
            ->get(['id', 'full_name', 'phone'])
            ->mapWithKeys(function (FleetDriver $driver): array {
                $label = trim((string) $driver->full_name);
                if ($driver->phone) {
                    $label .= ' · '.$driver->phone;
                }

                return [$driver->id => $label !== '' ? $label : 'Водитель #'.$driver->id];
            })
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $fromLegs
     * @param  list<array<string, mixed>>  $savedPerformers
     * @return list<array<string, mixed>>
     */
    private function mergeSavedPerformersIntoLegPayload(array $fromLegs, array $savedPerformers): array
    {
        if ($savedPerformers === []) {
            return $fromLegs;
        }

        $savedByStage = collect($savedPerformers)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->keyBy(fn (array $row): string => CargoPerformerAllocationNormalizer::normalizeStageIdentifier((string) ($row['stage'] ?? 'leg_1')));

        return collect($fromLegs)
            ->map(function (array $legRow) use ($savedByStage): array {
                $saved = $savedByStage->get(CargoPerformerAllocationNormalizer::normalizeStageIdentifier((string) ($legRow['stage'] ?? 'leg_1')));

                if (! is_array($saved)) {
                    return $legRow;
                }

                return $this->mergePerformerRow($legRow, $this->normalizePerformerRowFromWizardState($saved));
            })
            ->values()
            ->all();
    }
}
