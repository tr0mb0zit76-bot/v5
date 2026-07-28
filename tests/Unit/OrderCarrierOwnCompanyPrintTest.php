<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Models\Order;
use App\Services\OrderPrintFormDraftService;
use App\Support\OrderOwnCompanySide;
use App\Support\OrderPrintFormContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderCarrierOwnCompanyPrintTest extends TestCase
{
    public function test_id_for_print_party_prefers_carrier_own_company_on_carrier_side(): void
    {
        Schema::shouldReceive('hasColumn')
            ->with('orders', 'carrier_own_company_id')
            ->andReturn(true);

        $order = new Order([
            'own_company_id' => 10,
            'carrier_own_company_id' => 20,
        ]);

        $this->assertSame(10, OrderOwnCompanySide::idForPrintParty($order, 'customer'));
        $this->assertSame(20, OrderOwnCompanySide::idForPrintParty($order, 'carrier'));
        $this->assertSame(10, OrderOwnCompanySide::idForPrintParty($order, null));
    }

    public function test_without_subcontract_carrier_side_falls_back_to_own_company(): void
    {
        Schema::shouldReceive('hasColumn')
            ->with('orders', 'carrier_own_company_id')
            ->andReturn(true);

        $order = new Order([
            'own_company_id' => 10,
            'carrier_own_company_id' => null,
        ]);

        $this->assertSame(10, OrderOwnCompanySide::idForPrintParty($order, 'carrier'));
    }

    public function test_print_snapshot_swaps_own_company_by_print_party(): void
    {
        Schema::shouldReceive('hasColumn')->andReturnUsing(function (string $table, string $column): bool {
            if ($table === 'orders' && $column === 'carrier_own_company_id') {
                return true;
            }

            if ($table === 'orders' && $column === 'dispatcher_id') {
                return false;
            }

            if ($table === 'orders' && $column === 'cargo_declared_sum') {
                return false;
            }

            if (in_array($table, ['order_legs', 'route_points', 'cargos', 'leg_costs', 'leg_contractor_assignments', 'financial_terms'], true)) {
                return false;
            }

            return false;
        });

        Schema::shouldReceive('hasTable')->andReturn(false);

        $gen = new Contractor(['name' => 'ООО Автоальянс', 'inn' => '1']);
        $gen->id = 10;
        $sub = new Contractor(['name' => 'ООО Гросс', 'inn' => '2']);
        $sub->id = 20;

        $order = new Order([
            'own_company_id' => 10,
            'carrier_own_company_id' => 20,
        ]);
        $order->setRelation('ownCompany', $gen);
        $order->setRelation('carrierOwnCompany', $sub);
        $order->setRelation('client', null);
        $order->setRelation('carrier', null);
        $order->setRelation('manager', null);
        $order->setRelation('routePoints', new Collection);
        $order->setRelation('cargoItems', new Collection);

        $service = app(OrderPrintFormDraftService::class);
        $method = new \ReflectionMethod($service, 'buildSnapshot');
        $method->setAccessible(true);

        $customerSnapshot = $method->invoke(
            $service,
            $order,
            new OrderPrintFormContext(printParty: 'customer'),
        );
        $carrierSnapshot = $method->invoke(
            $service,
            $order,
            new OrderPrintFormContext(printParty: 'carrier'),
        );

        $this->assertSame('ООО Автоальянс', data_get($customerSnapshot, 'own_company.name'));
        $this->assertSame('ООО Гросс', data_get($carrierSnapshot, 'own_company.name'));
    }
}
