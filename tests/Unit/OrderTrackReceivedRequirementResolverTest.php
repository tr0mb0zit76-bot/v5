<?php

namespace Tests\Unit;

use App\Models\FinancialTerm;
use App\Models\Order;
use App\Support\OrderTrackReceivedRequirementResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTrackReceivedRequirementResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_ottn_schedule_requires_track_received(): void
    {
        $order = Order::factory()->create([
            'customer_payment_form' => 'bank_transfer',
            'payment_terms' => json_encode([
                'client' => [
                    'payment_schedule' => [
                        'installments' => [
                            ['percent' => 100, 'basis' => 'ottn', 'offset_days' => 3],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->assertTrue(OrderTrackReceivedRequirementResolver::orderNeedsCustomerTrackReceived($order));
        $this->assertFalse(OrderTrackReceivedRequirementResolver::orderNeedsCarrierTrackReceived($order));
    }

    public function test_cash_customer_schedule_does_not_require_track_received(): void
    {
        $order = Order::factory()->create([
            'customer_payment_form' => 'cash',
            'payment_terms' => json_encode([
                'client' => [
                    'payment_schedule' => [
                        'installments' => [
                            ['percent' => 100, 'basis' => 'ottn', 'offset_days' => 3],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->assertFalse(OrderTrackReceivedRequirementResolver::orderNeedsCustomerTrackReceived($order));
    }

    public function test_carrier_fttn_receipt_in_contractors_costs_requires_track_received(): void
    {
        $order = Order::factory()->create([
            'carrier_payment_form' => 'bank_transfer',
        ]);

        FinancialTerm::factory()->create([
            'order_id' => $order->id,
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

        $order->load('financialTerms');

        $this->assertFalse(OrderTrackReceivedRequirementResolver::orderNeedsCustomerTrackReceived($order));
        $this->assertTrue(OrderTrackReceivedRequirementResolver::orderNeedsCarrierTrackReceived($order));
    }
}
