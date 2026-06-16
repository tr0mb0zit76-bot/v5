<?php

namespace Tests\Unit;

use App\Support\PaymentScheduleSettlementStatus;
use Tests\TestCase;

class PaymentScheduleSettlementStatusTest extends TestCase
{
    public function test_detects_fully_settled_schedule(): void
    {
        $this->assertTrue(PaymentScheduleSettlementStatus::isFullySettled(617231, 617231, 0));
        $this->assertTrue(PaymentScheduleSettlementStatus::isFullySettled(617231, 617230.5, 0));
        $this->assertFalse(PaymentScheduleSettlementStatus::isFullySettled(617231, 300000, 317231));
        $this->assertFalse(PaymentScheduleSettlementStatus::isFullySettled(617231, 0, 617231));
    }
}
