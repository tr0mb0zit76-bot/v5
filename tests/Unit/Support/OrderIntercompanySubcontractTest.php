<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Order;
use App\Support\OrderIntercompanySubcontract;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderIntercompanySubcontractTest extends TestCase
{
    public function test_applies_when_own_and_carrier_own_differ(): void
    {
        if (! Schema::hasColumn('orders', 'carrier_own_company_id')) {
            $this->markTestSkipped('carrier_own_company_id missing');
        }

        $order = new Order([
            'own_company_id' => 10,
            'carrier_own_company_id' => 20,
            'customer_rate' => '100000.00',
        ]);

        $this->assertTrue(OrderIntercompanySubcontract::applies($order));
        $this->assertSame('95000.00', OrderIntercompanySubcontract::amount($order));
    }

    public function test_does_not_apply_when_same_company(): void
    {
        if (! Schema::hasColumn('orders', 'carrier_own_company_id')) {
            $this->markTestSkipped('carrier_own_company_id missing');
        }

        $order = new Order([
            'own_company_id' => 10,
            'carrier_own_company_id' => 10,
            'customer_rate' => '100000.00',
        ]);

        $this->assertFalse(OrderIntercompanySubcontract::applies($order));
    }

    public function test_does_not_apply_without_carrier_own(): void
    {
        $order = new Order([
            'own_company_id' => 10,
            'carrier_own_company_id' => null,
            'customer_rate' => '100000.00',
        ]);

        $this->assertFalse(OrderIntercompanySubcontract::applies($order));
    }

    public function test_amount_rounds_to_two_decimals(): void
    {
        $order = new Order(['customer_rate' => '100.33']);

        $this->assertSame('95.31', OrderIntercompanySubcontract::amount($order));
    }
}
