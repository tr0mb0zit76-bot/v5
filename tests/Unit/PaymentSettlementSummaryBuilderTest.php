<?php

namespace Tests\Unit;

use App\Services\PaymentSettlementSummaryBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PaymentSettlementSummaryBuilderTest extends TestCase
{
    #[Test]
    public function aggregate_marks_partial_when_half_paid(): void
    {
        $result = PaymentSettlementSummaryBuilder::aggregateFromAmounts(
            scheduledTotal: 1_000_000,
            paidTotal: 500_000,
            allRootsSettled: false,
        );

        $this->assertSame('partial', $result['state']);
        $this->assertEqualsWithDelta(50.0, $result['percent_paid'], 0.1);
        $this->assertFalse($result['complete']);
    }

    #[Test]
    public function aggregate_marks_complete_only_when_all_roots_settled_and_full_amount(): void
    {
        $result = PaymentSettlementSummaryBuilder::aggregateFromAmounts(
            scheduledTotal: 1_000_000,
            paidTotal: 1_000_000,
            allRootsSettled: true,
        );

        $this->assertSame('complete', $result['state']);
        $this->assertTrue($result['complete']);
    }

    #[Test]
    public function aggregate_not_complete_when_one_installment_paid_but_not_all_roots(): void
    {
        $result = PaymentSettlementSummaryBuilder::aggregateFromAmounts(
            scheduledTotal: 1_000_000,
            paidTotal: 500_000,
            allRootsSettled: true,
        );

        $this->assertSame('partial', $result['state']);
        $this->assertFalse($result['complete']);
    }
}
