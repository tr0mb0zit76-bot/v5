<?php

namespace Tests\Unit;

use App\Support\PaymentScheduleStructure;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OrderPaymentTermsConfigResolverTest extends TestCase
{
    #[Test]
    public function pick_richest_prefers_two_installments_over_empty_order_schedule(): void
    {
        $chosen = PaymentScheduleStructure::pickRichestSchedule([
            [],
            [
                'installments' => [
                    ['percent' => 40],
                    ['percent' => 60],
                ],
            ],
        ]);

        $this->assertCount(2, $chosen['installments'] ?? []);
    }

    #[Test]
    public function pick_richest_prefers_prepayment_split_over_single_postpayment(): void
    {
        $chosen = PaymentScheduleStructure::pickRichestSchedule([
            [
                'has_prepayment' => false,
                'postpayment_days' => 5,
            ],
            [
                'has_prepayment' => true,
                'prepayment_ratio' => 30,
                'prepayment_days' => 3,
                'postpayment_days' => 10,
            ],
        ]);

        $this->assertTrue((bool) ($chosen['has_prepayment'] ?? false));
        $this->assertEqualsWithDelta(30.0, (float) ($chosen['prepayment_ratio'] ?? 0), 0.01);
    }

    #[Test]
    public function defines_multiple_payments_for_installments_and_prepayment(): void
    {
        $this->assertTrue(PaymentScheduleStructure::definesMultiplePayments([
            'installments' => [
                ['percent' => 30],
                ['percent' => 70],
            ],
        ]));

        $this->assertTrue(PaymentScheduleStructure::definesMultiplePayments([
            'has_prepayment' => true,
            'prepayment_ratio' => 30,
        ]));

        $this->assertFalse(PaymentScheduleStructure::definesMultiplePayments([
            'has_prepayment' => false,
            'postpayment_days' => 5,
        ]));
    }
}
