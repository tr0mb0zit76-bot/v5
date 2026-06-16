<?php

namespace Tests\Unit;

use App\Support\PaymentScheduleSettlementStatus;
use Tests\TestCase;

class PaymentScheduleSettlementStatusTest extends TestCase
{
    public function test_outstanding_amount_treats_zero_remaining_as_uninitialized_when_unpaid(): void
    {
        $this->assertSame(400000.0, PaymentScheduleSettlementStatus::outstandingAmount(400000, 0, 0));
        $this->assertSame(400000.0, PaymentScheduleSettlementStatus::outstandingAmount(400000, 0, null));
    }

    public function test_outstanding_amount_uses_positive_remaining(): void
    {
        $this->assertSame(300000.0, PaymentScheduleSettlementStatus::outstandingAmount(400000, 100000, 300000));
    }

    public function test_outstanding_amount_derives_from_paid_when_remaining_zero(): void
    {
        $this->assertSame(300000.0, PaymentScheduleSettlementStatus::outstandingAmount(400000, 100000, 0));
    }

    public function test_detects_fully_settled_schedule(): void
    {
        $this->assertTrue(PaymentScheduleSettlementStatus::isFullySettled(617231, 617231, 0));
        $this->assertTrue(PaymentScheduleSettlementStatus::isFullySettled(617231, 617230.5, 0));
        $this->assertFalse(PaymentScheduleSettlementStatus::isFullySettled(617231, 300000, 317231));
        $this->assertFalse(PaymentScheduleSettlementStatus::isFullySettled(617231, 0, 617231));
    }
}
