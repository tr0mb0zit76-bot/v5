<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;
use App\Services\OrderDocumentRequirementService;
use Illuminate\Support\Collection;

/**
 * Какие колонки грида «Документы» обязательны по заказу (false → «не требуется» в ячейке).
 */
final class DocumentRegistryGridColumnApplicabilityResolver
{
    /** @var list<string> */
    private const REQUEST_CUSTOMER_COLUMNS = [
        'customer_request',
        'customer_contract_request',
    ];

    /** @var list<string> */
    private const REQUEST_CARRIER_COLUMNS = [
        'carrier_request',
        'carrier_contract_request',
    ];

    /** @var list<string> */
    private const OWN_FLEET_CARRIER_GRID_COLUMNS = [
        'carrier_invoice',
        'carrier_upd',
        'carrier_act',
        'carrier_invoice_factura',
        'carrier_request',
        'carrier_contract_request',
        'transport_docs',
    ];

    public function __construct(
        private readonly OrderDocumentRequirementService $requirementService,
    ) {}

    /**
     * @return array<string, bool>
     */
    public function mapForOrder(Order $order): array
    {
        $map = self::mapFromRules($this->requirementService->requirementRulesForOrder($order));

        if (self::orderIsOwnFleetCarrierOnly($order)) {
            foreach (self::OWN_FLEET_CARRIER_GRID_COLUMNS as $column) {
                $map[$column] = false;
            }
        }

        return $map;
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array<int, array<string, bool>>
     */
    public function mapForOrders(Collection $orders): array
    {
        if ($orders->isEmpty()) {
            return [];
        }

        $map = [];

        foreach ($orders as $order) {
            $map[(int) $order->id] = $this->mapForOrder($order);
        }

        return $map;
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     * @return array<string, bool>
     */
    public static function mapFromRules(array $rules): array
    {
        $slotKinds = [];
        /** @var array<string, true> $customerClosingTypes */
        $customerClosingTypes = [];
        /** @var array<string, true> $carrierClosingTypes */
        $carrierClosingTypes = [];

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $kind = isset($rule['slot_kind']) ? trim((string) $rule['slot_kind']) : '';

            if ($kind !== '') {
                $slotKinds[$kind] = true;
            }

            if ($kind === 'customer_closing') {
                foreach (OrderDocumentClosingFulfillment::acceptedTypes($rule) as $type) {
                    $customerClosingTypes[$type] = true;
                }
            }

            if ($kind === 'carrier_closing') {
                foreach (OrderDocumentClosingFulfillment::acceptedTypes($rule) as $type) {
                    $carrierClosingTypes[$type] = true;
                }
            }
        }

        $has = static fn (string $kind): bool => isset($slotKinds[$kind]);

        $map = [];

        $map['customer_upd'] = $has('customer_closing') && isset($customerClosingTypes['upd']);
        $map['customer_act'] = $has('customer_closing') && isset($customerClosingTypes['act']);
        $map['customer_invoice_factura'] = $has('customer_closing') && isset($customerClosingTypes['invoice_factura']);

        $map['carrier_invoice'] = $has('carrier_closing');
        $map['carrier_upd'] = $has('carrier_closing') && isset($carrierClosingTypes['upd']);
        $map['carrier_act'] = $has('carrier_closing') && isset($carrierClosingTypes['act']);
        $map['carrier_invoice_factura'] = $has('carrier_closing') && isset($carrierClosingTypes['invoice_factura']);

        foreach (self::REQUEST_CUSTOMER_COLUMNS as $column) {
            $map[$column] = $has('customer_request');
        }

        foreach (self::REQUEST_CARRIER_COLUMNS as $column) {
            $map[$column] = $has('carrier_request');
        }

        return $map;
    }

    public static function orderIsOwnFleetCarrierOnly(Order $order): bool
    {
        $performers = $order->performers;

        if (! is_array($performers) || $performers === []) {
            return false;
        }

        return OwnFleetCatalog::isOwnFleetCarrierOnly($performers);
    }
}
