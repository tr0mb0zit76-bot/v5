<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderDocument;
use App\Services\OrderStatusService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class OrderStatusServiceTest extends TestCase
{
    public function test_it_marks_order_as_documents_when_unloading_exists_but_required_documents_are_incomplete(): void
    {
        $order = $this->makeOrder([
            'loading_date' => '2026-04-01',
            'unloading_date' => '2026-04-02',
            'payment_statuses' => [],
            'salary_paid' => 0,
        ], [
            ['type' => 'request', 'metadata' => ['party' => 'customer'], 'status' => 'draft'],
            ['type' => 'request', 'metadata' => ['party' => 'carrier'], 'status' => 'draft'],
        ]);

        $status = app(OrderStatusService::class)->describe($order);

        $this->assertSame('documents', $status['status']);
        $this->assertSame('Документы', $status['label']);
        $this->assertFalse($status['required_documents_completed']);
        $this->assertNotEmpty($status['messages']);
    }

    public function test_it_marks_order_as_closed_when_documents_and_payments_are_complete(): void
    {
        $order = $this->makeOrder([
            'loading_date' => '2026-04-01',
            'unloading_date' => '2026-04-02',
            'payment_statuses' => [
                'customer' => ['status' => 'paid'],
                'carrier' => ['paid' => true],
            ],
            'salary_paid' => 15000,
        ], [
            ['type' => 'request', 'metadata' => ['party' => 'customer'], 'status' => 'sent'],
            ['type' => 'request', 'metadata' => ['party' => 'carrier'], 'status' => 'sent'],
            ['type' => 'waybill', 'metadata' => ['party' => 'internal'], 'status' => 'sent'],
            ['type' => 'upd', 'metadata' => ['party' => 'customer'], 'status' => 'sent'],
            ['type' => 'act', 'metadata' => ['party' => 'carrier'], 'status' => 'sent'],
        ]);

        $status = app(OrderStatusService::class)->describe($order);

        $this->assertSame('closed', $status['status']);
        $this->assertTrue($status['required_documents_completed']);
        $this->assertTrue($status['customer_paid']);
        $this->assertTrue($status['carrier_paid']);
        $this->assertTrue($status['manager_paid']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $documents
     */
    private function makeOrder(array $attributes, array $documents): Order
    {
        $order = new Order($attributes);
        $order->setRelation('documents', new Collection(
            array_map(fn (array $document): OrderDocument => new OrderDocument($document), $documents)
        ));

        return $order;
    }
}
