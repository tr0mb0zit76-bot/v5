<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Support\OrderPersistedId;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderPersistedIdTest extends TestCase
{
    #[Test]
    public function test_resolves_id_from_model_key(): void
    {
        $order = new Order;
        $order->forceFill(['id' => 42]);
        $order->exists = true;

        $this->assertSame(42, OrderPersistedId::resolve($order));
        $this->assertSame(42, OrderPersistedId::resolveOrFail($order));
    }

    #[Test]
    public function test_returns_null_when_order_has_no_key(): void
    {
        $order = new Order;

        $this->assertNull(OrderPersistedId::resolve($order));
    }

    #[Test]
    public function test_resolve_or_fail_throws_when_id_missing(): void
    {
        $order = new Order;

        $this->expectException(InvalidArgumentException::class);

        OrderPersistedId::resolveOrFail($order);
    }

    #[Test]
    public function test_resolves_from_persisted_order(): void
    {
        $order = Order::factory()->create();

        $this->assertSame($order->id, OrderPersistedId::resolve($order));
        $this->assertSame($order->id, OrderPersistedId::resolveOrFail($order));
    }
}
