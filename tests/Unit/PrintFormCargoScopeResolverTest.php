<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Support\OrderPrintFormContext;
use App\Support\PrintFormCargoScopeResolver;
use Tests\TestCase;

class PrintFormCargoScopeResolverTest extends TestCase
{
    public function test_resolves_carrier_slot_from_split_performers(): void
    {
        $order = new Order([
            'performers' => [
                [
                    'stage' => 'Плечо 2',
                    'carrier_mode' => 'split',
                    'split_carriers' => [
                        ['slot' => 1, 'contractor_id' => 12],
                        ['slot' => 2, 'contractor_id' => 16],
                    ],
                ],
            ],
        ]);

        $context = new OrderPrintFormContext(
            legStage: 'leg_2',
            carrierContractorId: 16,
        );

        $this->assertSame(2, PrintFormCargoScopeResolver::resolveCarrierSlot($order, $context));
    }

    public function test_resolves_scope_for_split_carrier_on_leg(): void
    {
        $order = new Order([
            'performers' => [
                ['stage' => 'leg_1', 'carrier_mode' => 'single', 'contractor_id' => 2],
                [
                    'stage' => 'leg_2',
                    'carrier_mode' => 'split',
                    'split_carriers' => [
                        ['slot' => 1, 'contractor_id' => 12],
                        ['slot' => 2, 'contractor_id' => 16],
                    ],
                ],
            ],
        ]);

        $cargo = (object) [
            'ati_cargo_payload' => [
                'performer_allocations' => [
                    ['stage' => 'leg_1', 'carrier_slot' => null, 'package_count' => 5, 'weight_value' => 88000],
                    ['stage' => 'leg_2', 'carrier_slot' => 1, 'package_count' => 3, 'weight_value' => 52800],
                    ['stage' => 'leg_2', 'carrier_slot' => 2, 'package_count' => 2, 'weight_value' => 35200],
                ],
            ],
        ];

        $carrierOneContext = new OrderPrintFormContext(
            legStage: 'leg_2',
            carrierContractorId: 12,
        );
        $carrierTwoContext = new OrderPrintFormContext(
            legStage: 'leg_2',
            carrierContractorId: 16,
        );

        $scopeOne = PrintFormCargoScopeResolver::resolveScopeForCargo($order, $cargo, $carrierOneContext);
        $scopeTwo = PrintFormCargoScopeResolver::resolveScopeForCargo($order, $cargo, $carrierTwoContext);

        $this->assertSame(3.0, $scopeOne['package_count']);
        $this->assertSame(52800.0, $scopeOne['weight_value']);
        $this->assertSame(2.0, $scopeTwo['package_count']);
        $this->assertSame(35200.0, $scopeTwo['weight_value']);
    }

    public function test_aggregates_customer_leg_scope_without_carrier(): void
    {
        $order = new Order(['performers' => []]);
        $cargo = (object) [
            'ati_cargo_payload' => [
                'performer_allocations' => [
                    ['stage' => 'leg_2', 'carrier_slot' => 1, 'package_count' => 3, 'weight_value' => 3000],
                    ['stage' => 'leg_2', 'carrier_slot' => 2, 'package_count' => 2, 'weight_value' => 2000],
                ],
            ],
        ];

        $context = OrderPrintFormContext::forCustomerLeg('leg_2');
        $scope = PrintFormCargoScopeResolver::resolveScopeForCargo($order, $cargo, $context);

        $this->assertSame(5.0, $scope['package_count']);
        $this->assertSame(5000.0, $scope['weight_value']);
    }

    public function test_returns_null_scope_when_no_allocations(): void
    {
        $order = new Order(['performers' => []]);
        $cargo = (object) ['ati_cargo_payload' => []];
        $context = new OrderPrintFormContext(legStage: 'leg_1', carrierContractorId: 2);

        $this->assertNull(PrintFormCargoScopeResolver::resolveScopeForCargo($order, $cargo, $context));
    }
}
