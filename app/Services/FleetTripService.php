<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FinancialTerm;
use App\Models\FleetTrip;
use App\Models\FleetTripCostLine;
use App\Models\Order;
use App\Support\CarrierRateFromFinancialTerms;
use App\Support\ContractorCostRowClassification;
use App\Support\OwnFleetCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FleetTripService
{
    public function __construct(
        private readonly OwnFleetContractorService $ownFleetContractorService,
        private readonly OrderCompensationService $orderCompensationService,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $performers
     * @param  list<array<string, mixed>>  $contractorsCosts
     */
    public function syncPlannedTripsFromOrder(Order $order, array $performers, array $contractorsCosts): void
    {
        if (! Schema::hasTable('fleet_trips')) {
            return;
        }

        $ownFleetContractorId = $this->ownFleetContractorService->contractorId();

        foreach ($performers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            $stage = $this->normalizeStage((string) ($performer['stage'] ?? 'leg_1'));

            if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                foreach ($performer['split_carriers'] as $slot) {
                    if (! is_array($slot)) {
                        continue;
                    }

                    $carrierSlot = (int) ($slot['slot'] ?? 1);
                    if (! OwnFleetCatalog::isOwnFleetExecutionMode(isset($slot['execution_mode']) ? (string) $slot['execution_mode'] : null)) {
                        continue;
                    }

                    $this->upsertPlannedTrip(
                        $order,
                        $stage,
                        $carrierSlot,
                        isset($slot['fleet_vehicle_id']) ? (int) $slot['fleet_vehicle_id'] : null,
                        isset($slot['fleet_driver_id']) ? (int) $slot['fleet_driver_id'] : null,
                        $this->estimatedCostForSlot($contractorsCosts, $stage, $carrierSlot, $ownFleetContractorId),
                    );
                }

                continue;
            }

            if (! OwnFleetCatalog::isOwnFleetExecutionMode(isset($performer['execution_mode']) ? (string) $performer['execution_mode'] : null)) {
                continue;
            }

            $this->upsertPlannedTrip(
                $order,
                $stage,
                null,
                isset($performer['fleet_vehicle_id']) ? (int) $performer['fleet_vehicle_id'] : null,
                isset($performer['fleet_driver_id']) ? (int) $performer['fleet_driver_id'] : null,
                $this->estimatedCostForSlot($contractorsCosts, $stage, null, $ownFleetContractorId),
            );
        }
    }

    public function completeTrip(FleetTrip $trip): FleetTrip
    {
        return DB::transaction(function () use ($trip): FleetTrip {
            $trip->load('costLines');
            $total = round((float) $trip->costLines->sum(fn (FleetTripCostLine $line): float => (float) $line->amount), 2);

            $trip->update([
                'status' => 'completed',
                'total_cost' => $total,
                'completed_at' => $trip->completed_at ?? now(),
            ]);

            $this->syncActualCostToOrder($trip->fresh(['costLines']));

            return $trip->fresh(['costLines', 'order', 'vehicle', 'driver']);
        });
    }

    public function syncActualCostToOrder(FleetTrip $trip): void
    {
        if (! Schema::hasTable('financial_terms')) {
            return;
        }

        $order = $trip->order ?? Order::query()->find($trip->order_id);
        if ($order === null) {
            return;
        }

        $financialTerm = FinancialTerm::query()->where('order_id', $order->id)->first();
        if ($financialTerm === null) {
            return;
        }

        $costs = is_array($financialTerm->contractors_costs) ? $financialTerm->contractors_costs : [];
        $ownFleetId = $this->ownFleetContractorService->contractorId();
        $actualAmount = (float) ($trip->total_cost ?? 0);

        $updated = collect($costs)
            ->map(function (array $cost) use ($trip, $ownFleetId, $actualAmount): array {
                if (ContractorCostRowClassification::isAdditional($cost)) {
                    return $cost;
                }

                $stage = $this->normalizeStage((string) ($cost['stage'] ?? 'leg_1'));
                $slot = isset($cost['carrier_slot']) && $cost['carrier_slot'] !== null && $cost['carrier_slot'] !== ''
                    ? (int) $cost['carrier_slot']
                    : null;

                $matchesSlot = $slot === $trip->carrier_slot
                    || ($slot === null && $trip->carrier_slot === null);
                $matchesStage = $stage === $this->normalizeStage($trip->order_leg_stage);
                $isOwnFleetRow = OwnFleetCatalog::isOwnFleetExecutionMode($cost['execution_mode'] ?? null)
                    || ($ownFleetId !== null && (int) ($cost['contractor_id'] ?? 0) === $ownFleetId);

                if ($matchesStage && $matchesSlot && $isOwnFleetRow) {
                    $cost['amount'] = $actualAmount;
                    $cost['execution_mode'] = OwnFleetCatalog::EXECUTION_MODE_OWN_FLEET;
                }

                return $cost;
            })
            ->all();

        $carrierRate = CarrierRateFromFinancialTerms::sumContractorsCostsAmounts($updated) ?? 0.0;

        $financialTerm->update(['contractors_costs' => $updated]);

        $order = $order->fresh(['financialTerms']);
        $calculation = $this->orderCompensationService->calculateOrder($order);

        $order->forceFill([
            'carrier_rate' => $carrierRate > 0 ? $carrierRate : null,
            'kpi_percent' => $calculation['kpi_percent'],
            'delta' => $calculation['delta'],
            'salary_accrued' => $calculation['salary_accrued'],
        ])->saveQuietly();
    }

    /**
     * @param  list<array<string, mixed>>  $contractorsCosts
     */
    private function estimatedCostForSlot(array $contractorsCosts, string $stage, ?int $carrierSlot, ?int $ownFleetContractorId): ?float
    {
        foreach ($contractorsCosts as $cost) {
            if (! is_array($cost) || ContractorCostRowClassification::isAdditional($cost)) {
                continue;
            }

            $costStage = $this->normalizeStage((string) ($cost['stage'] ?? 'leg_1'));
            $costSlot = isset($cost['carrier_slot']) && $cost['carrier_slot'] !== null && $cost['carrier_slot'] !== ''
                ? (int) $cost['carrier_slot']
                : null;

            if ($costStage !== $stage || $costSlot !== $carrierSlot) {
                continue;
            }

            if (OwnFleetCatalog::isOwnFleetExecutionMode($cost['execution_mode'] ?? null)
                || ($ownFleetContractorId !== null && (int) ($cost['contractor_id'] ?? 0) === $ownFleetContractorId)) {
                $amount = $cost['amount'] ?? null;

                return $amount !== null && $amount !== '' ? (float) $amount : null;
            }
        }

        return null;
    }

    private function upsertPlannedTrip(
        Order $order,
        string $stage,
        ?int $carrierSlot,
        ?int $vehicleId,
        ?int $driverId,
        ?float $estimatedCost,
    ): void {
        $trip = FleetTrip::query()->firstOrNew([
            'order_id' => $order->id,
            'order_leg_stage' => $stage,
            'carrier_slot' => $carrierSlot,
        ]);

        if ($trip->exists && $trip->status === 'completed') {
            return;
        }

        $trip->fill([
            'fleet_vehicle_id' => $vehicleId,
            'fleet_driver_id' => $driverId,
            'status' => $trip->status ?? 'planned',
            'estimated_cost' => $estimatedCost ?? $trip->estimated_cost,
        ]);

        if (! $trip->exists) {
            $trip->status = 'planned';
        }

        $trip->save();
    }

    private function normalizeStage(string $stage): string
    {
        if (preg_match('/^Плечо (\d+)$/u', $stage, $matches) === 1) {
            return 'leg_'.$matches[1];
        }

        if (preg_match('/^leg_\d+$/', $stage) === 1) {
            return $stage;
        }

        return $stage !== '' ? $stage : 'leg_1';
    }

    /**
     * @return array{
     *     trip_count: int,
     *     completed_count: int,
     *     total_actual_cost: float,
     *     total_actual_km: int,
     *     rub_per_km: float|null,
     *     own_fleet_order_share_percent: float|null
     * }
     */
    public function efficiencySummary(): array
    {
        if (! Schema::hasTable('fleet_trips')) {
            return [
                'trip_count' => 0,
                'completed_count' => 0,
                'total_actual_cost' => 0.0,
                'total_actual_km' => 0,
                'rub_per_km' => null,
                'own_fleet_order_share_percent' => null,
            ];
        }

        $completedQuery = FleetTrip::query()->where('status', 'completed');
        $tripCount = FleetTrip::query()->count();
        $completedCount = (clone $completedQuery)->count();
        $totalCost = (float) (clone $completedQuery)->sum('total_cost');
        $totalKm = (int) (clone $completedQuery)->sum('actual_km');

        $rubPerKm = $totalKm > 0 ? round($totalCost / $totalKm, 2) : null;

        $ordersWithOwnFleet = FleetTrip::query()->distinct('order_id')->count('order_id');
        $totalOrders = Schema::hasTable('orders') ? Order::query()->count() : 0;
        $share = $totalOrders > 0 ? round($ordersWithOwnFleet / $totalOrders * 100, 1) : null;

        return [
            'trip_count' => $tripCount,
            'completed_count' => $completedCount,
            'total_actual_cost' => round($totalCost, 2),
            'total_actual_km' => $totalKm,
            'rub_per_km' => $rubPerKm,
            'own_fleet_order_share_percent' => $share,
        ];
    }
}
