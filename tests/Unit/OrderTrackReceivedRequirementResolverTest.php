<?php

namespace Tests\Unit;

use App\Models\FinancialTerm;
use App\Models\Order;
use App\Support\OrderTrackReceivedRequirementResolver;
use Tests\TestCase;

class OrderTrackReceivedRequirementResolverTest extends TestCase
{
    public function test_customer_ottn_schedule_requires_track_received(): void
    {
        $order = $this->createOrderWithClientSchedule([
            'installments' => [
                ['percent' => 100, 'basis' => 'ottn', 'offset_days' => 3],
            ],
        ], [
            'customer_payment_form' => 'bank_transfer',
        ]);

        $this->assertTrue(OrderTrackReceivedRequirementResolver::orderNeedsCustomerTrackReceived($order));
        $this->assertFalse(OrderTrackReceivedRequirementResolver::orderNeedsCarrierTrackReceived($order));
    }

    public function test_cash_customer_fttn_schedule_does_not_require_track_received(): void
    {
        $order = $this->createOrderWithClientSchedule([
            'installments' => [
                ['percent' => 100, 'basis' => 'fttn', 'offset_days' => 3],
            ],
        ], [
            'customer_payment_form' => 'cash',
        ]);

        $this->assertFalse(OrderTrackReceivedRequirementResolver::orderNeedsCustomerTrackReceived($order));
    }

    public function test_cash_customer_ottn_schedule_requires_track_received(): void
    {
        $order = $this->createOrderWithClientSchedule([
            'installments' => [
                ['percent' => 100, 'basis' => 'ottn', 'offset_days' => 5],
            ],
        ], [
            'customer_payment_form' => 'cash',
        ]);

        $this->assertTrue(OrderTrackReceivedRequirementResolver::orderNeedsCustomerTrackReceived($order));
    }

    public function test_carrier_fttn_receipt_in_contractors_costs_requires_track_received(): void
    {
        $orderId = $this->insertOrderRow([]);
        $order = Order::query()->findOrFail($orderId);
        $order->wizard_state = [
            'financial_term' => [
                'client_payment_schedule' => [
                    'installments' => [
                        ['percent' => 100, 'basis' => 'fttn', 'offset_days' => 0],
                    ],
                ],
            ],
        ];
        $order->save();

        $financialTerm = FinancialTerm::factory()->create([
            'order_id' => $orderId,
            'payment_terms_snapshot' => null,
            'contractors_costs' => [
                [
                    'contractor_id' => 50,
                    'payment_form' => 'bank_transfer',
                    'payment_schedule' => [
                        'installments' => [
                            ['percent' => 100, 'basis' => 'fttn_receipt', 'offset_days' => 5],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertFalse(
            OrderTrackReceivedRequirementResolver::orderNeedsCustomerTrackReceived($order, $financialTerm),
        );
        $this->assertTrue(
            OrderTrackReceivedRequirementResolver::orderNeedsCarrierTrackReceived($order, $financialTerm),
        );
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @param  array<string, mixed>  $orderAttributes
     */
    private function createOrderWithClientSchedule(array $schedule, array $orderAttributes = []): Order
    {
        $orderId = $this->insertOrderRow($orderAttributes);
        $order = Order::query()->findOrFail($orderId);
        $order->wizard_state = [
            'financial_term' => [
                'client_payment_schedule' => $schedule,
            ],
        ];
        $order->save();

        return $order->fresh();
    }
}
