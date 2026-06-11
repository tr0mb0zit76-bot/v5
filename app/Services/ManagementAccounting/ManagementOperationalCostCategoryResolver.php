<?php

namespace App\Services\ManagementAccounting;

use App\Models\ManagementExpenseCategory;
use App\Models\Order;
use App\Services\OwnFleetContractorService;
use App\Support\ManagementCostCategoryCodes;
use App\Support\OwnFleetCatalog;
use Illuminate\Support\Facades\Schema;

class ManagementOperationalCostCategoryResolver
{
    public function __construct(
        private readonly OwnFleetContractorService $ownFleetContractorService,
    ) {}

    public function categoryCodeForCarrier(?int $orderId = null, ?int $contractorId = null): string
    {
        if ($this->isOwnFleetContractor($contractorId)) {
            return ManagementCostCategoryCodes::OWN_FLEET;
        }

        if ($orderId !== null && $this->orderUsesOwnFleetCarrier($orderId)) {
            return ManagementCostCategoryCodes::OWN_FLEET;
        }

        return ManagementCostCategoryCodes::HIRED_TRANSPORT;
    }

    public function categoryIdForCarrier(?int $orderId = null, ?int $contractorId = null): ?int
    {
        if (! Schema::hasTable('management_expense_categories')) {
            return null;
        }

        $code = $this->categoryCodeForCarrier($orderId, $contractorId);

        $id = ManagementExpenseCategory::query()->where('code', $code)->value('id');

        if ($id === null && $code === ManagementCostCategoryCodes::OWN_FLEET) {
            $id = ManagementExpenseCategory::query()
                ->where('code', ManagementCostCategoryCodes::HIRED_TRANSPORT)
                ->value('id');
        }

        return $id !== null ? (int) $id : null;
    }

    private function isOwnFleetContractor(?int $contractorId): bool
    {
        if ($contractorId === null) {
            return false;
        }

        $ownFleetId = $this->ownFleetContractorService->contractorId();

        return $ownFleetId !== null && (int) $contractorId === (int) $ownFleetId;
    }

    private function orderUsesOwnFleetCarrier(int $orderId): bool
    {
        if (! Schema::hasTable('orders')) {
            return false;
        }

        $order = Order::query()->find($orderId);
        if ($order === null) {
            return false;
        }

        $hasOwnFleet = false;
        $hasHired = false;

        foreach ($this->performerRows($order) as $row) {
            if (OwnFleetCatalog::performerRowIsOwnFleet($row)) {
                $hasOwnFleet = true;

                continue;
            }

            if (filled($row['contractor_id'] ?? null)) {
                $hasHired = true;
            }
        }

        if ($hasOwnFleet && ! $hasHired) {
            return true;
        }

        $ownFleetId = $this->ownFleetContractorService->contractorId();

        return $ownFleetId !== null && (int) ($order->carrier_id ?? 0) === (int) $ownFleetId;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function performerRows(Order $order): array
    {
        $performers = is_array($order->performers) ? $order->performers : [];
        $rows = [];

        foreach ($performers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            $rows[] = $performer;

            foreach ($performer['split_carriers'] ?? [] as $slot) {
                if (is_array($slot)) {
                    $rows[] = $slot;
                }
            }
        }

        return $rows;
    }
}
