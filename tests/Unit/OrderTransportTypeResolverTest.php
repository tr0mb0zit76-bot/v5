<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Support\OrderTransportTypeResolver;
use App\Support\OwnFleetCatalog;
use Tests\TestCase;

class OrderTransportTypeResolverTest extends TestCase
{
    private OrderTransportTypeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new OrderTransportTypeResolver;
    }

    public function test_labels_own_fleet_performer(): void
    {
        $order = new Order([
            'performers' => [
                ['execution_mode' => OwnFleetCatalog::EXECUTION_MODE_OWN_FLEET],
            ],
        ]);

        $this->assertSame(OrderTransportTypeResolver::LABEL_OWN_FLEET, $this->resolver->labelForOrder($order));
    }

    public function test_labels_hired_when_carrier_id_present(): void
    {
        $order = new Order([
            'carrier_id' => 10,
            'performers' => [],
        ]);

        $this->assertSame(OrderTransportTypeResolver::LABEL_HIRED, $this->resolver->labelForOrder($order));
    }

    public function test_labels_mixed_when_own_and_hired(): void
    {
        $order = new Order([
            'performers' => [
                [
                    'execution_mode' => OwnFleetCatalog::EXECUTION_MODE_OWN_FLEET,
                    'carrier_mode' => 'split',
                    'split_carriers' => [
                        ['contractor_id' => 5, 'contractor_name' => 'ООО Перевозчик'],
                    ],
                ],
            ],
        ]);

        $this->assertSame(OrderTransportTypeResolver::LABEL_MIXED, $this->resolver->labelForOrder($order));
    }
}
