<?php

namespace App\Support;

use App\Models\Order;

/**
 * Подставляет в печатные формы количество/вес груза по performer_allocations (плечо, слот split).
 */
final class PrintFormCargoScopeResolver
{
    public static function scopesCargo(?OrderPrintFormContext $context): bool
    {
        if ($context === null) {
            return false;
        }

        if ($context->routeLegsAsTableRows) {
            return false;
        }

        return ($context->legStage !== null && $context->legStage !== '')
            || ($context->carrierContractorId !== null && $context->carrierContractorId > 0);
    }

    /**
     * @return array{package_count: float, weight_value: float|null}|null null — брать строку заказчика целиком
     */
    public static function resolveScopeForCargo(Order $order, mixed $cargo, ?OrderPrintFormContext $context): ?array
    {
        if (! self::scopesCargo($context) || $context === null) {
            return null;
        }

        $allocations = self::performerAllocationsFromCargo($cargo);
        if ($allocations === []) {
            return null;
        }

        $legStage = $context->legStage !== null && $context->legStage !== ''
            ? CargoPerformerAllocationNormalizer::normalizeStageIdentifier($context->legStage)
            : null;

        if ($context->carrierContractorId !== null && $context->carrierContractorId > 0 && $legStage !== null) {
            $carrierSlot = self::resolveCarrierSlot($order, $context, $legStage);
            $match = self::findAllocation($allocations, $legStage, $carrierSlot);

            return $match !== null ? self::normalizeScopeRow($match) : null;
        }

        if ($legStage !== null) {
            return self::aggregateAllocationsForStage($allocations, $legStage);
        }

        return null;
    }

    public static function resolveCarrierSlot(Order $order, OrderPrintFormContext $context, ?string $normalizedLegStage = null): ?int
    {
        if ($context->carrierSlot !== null && $context->carrierSlot > 0) {
            return $context->carrierSlot;
        }

        if ($context->carrierContractorId === null || $context->carrierContractorId <= 0) {
            return null;
        }

        $targetStage = $normalizedLegStage ?? (
            $context->legStage !== null && $context->legStage !== ''
                ? CargoPerformerAllocationNormalizer::normalizeStageIdentifier($context->legStage)
                : null
        );

        if ($targetStage === null) {
            return null;
        }

        $performers = is_array($order->performers) ? $order->performers : [];

        foreach ($performers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            $stage = CargoPerformerAllocationNormalizer::normalizeStageIdentifier((string) ($performer['stage'] ?? ''));
            if ($stage !== $targetStage) {
                continue;
            }

            if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                foreach ($performer['split_carriers'] as $slot) {
                    if (! is_array($slot)) {
                        continue;
                    }

                    if ((int) ($slot['contractor_id'] ?? 0) === $context->carrierContractorId) {
                        $slotNumber = (int) ($slot['slot'] ?? 0);

                        return $slotNumber > 0 ? $slotNumber : null;
                    }
                }
            }

            return null;
        }

        return null;
    }

    /**
     * @return list<array{stage: string, carrier_slot: int|null, package_count: float|null, weight_value: float|null}>
     */
    public static function performerAllocationsFromCargo(mixed $cargo): array
    {
        if (! is_object($cargo)) {
            return [];
        }

        $payload = $cargo->ati_cargo_payload ?? null;
        if (! is_array($payload) || array_is_list($payload)) {
            return [];
        }

        $raw = $payload['performer_allocations'] ?? null;

        return is_array($raw) ? CargoPerformerAllocationNormalizer::normalizeForStorage($raw) : [];
    }

    /**
     * @param  list<array{stage: string, carrier_slot: int|null, package_count: float|null, weight_value: float|null}>  $allocations
     * @return array{stage: string, carrier_slot: int|null, package_count: float|null, weight_value: float|null}|null
     */
    private static function findAllocation(array $allocations, string $legStage, ?int $carrierSlot): ?array
    {
        foreach ($allocations as $row) {
            if (CargoPerformerAllocationNormalizer::normalizeStageIdentifier((string) ($row['stage'] ?? '')) !== $legStage) {
                continue;
            }

            $rowSlot = $row['carrier_slot'] ?? null;

            if ($carrierSlot === null) {
                if ($rowSlot === null) {
                    return $row;
                }

                continue;
            }

            if ($rowSlot !== null && (int) $rowSlot === $carrierSlot) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param  list<array{stage: string, carrier_slot: int|null, package_count: float|null, weight_value: float|null}>  $allocations
     * @return array{package_count: float, weight_value: float|null}|null
     */
    private static function aggregateAllocationsForStage(array $allocations, string $legStage): ?array
    {
        $packageCount = 0.0;
        $weightValue = 0.0;
        $hasWeight = false;
        $matched = false;

        foreach ($allocations as $row) {
            if (CargoPerformerAllocationNormalizer::normalizeStageIdentifier((string) ($row['stage'] ?? '')) !== $legStage) {
                continue;
            }

            $matched = true;
            $packages = $row['package_count'] ?? null;
            if ($packages !== null && is_numeric($packages)) {
                $packageCount += (float) $packages;
            }

            $weight = $row['weight_value'] ?? null;
            if ($weight !== null && is_numeric($weight)) {
                $weightValue += (float) $weight;
                $hasWeight = true;
            }
        }

        if (! $matched) {
            return null;
        }

        return [
            'package_count' => $packageCount,
            'weight_value' => $hasWeight ? $weightValue : null,
        ];
    }

    /**
     * @param  array{stage: string, carrier_slot: int|null, package_count: float|null, weight_value: float|null}  $row
     * @return array{package_count: float, weight_value: float|null}
     */
    private static function normalizeScopeRow(array $row): array
    {
        $packageCount = $row['package_count'] ?? null;
        $weightValue = $row['weight_value'] ?? null;

        return [
            'package_count' => is_numeric($packageCount) ? (float) $packageCount : 0.0,
            'weight_value' => is_numeric($weightValue) ? (float) $weightValue : null,
        ];
    }
}
