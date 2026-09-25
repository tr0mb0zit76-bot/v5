<?php

namespace Tests\Unit\Support;

use App\Support\PaymentInstallmentScheduleNormalizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentInstallmentScheduleNormalizerTest extends TestCase
{
    #[Test]
    public function it_preserves_exact_amounts_when_they_sum_to_total(): void
    {
        $schedule = [
            'installments' => [
                ['percent' => 25, 'amount' => 500000, 'basis' => 'fttn', 'anchor' => 'order_date', 'offset_days' => -7, 'offset_unit' => 'bank_days'],
                ['percent' => 25, 'amount' => 1856500, 'basis' => 'fttn', 'anchor' => 'loading_date', 'offset_days' => 1, 'offset_unit' => 'bank_days'],
                ['percent' => 25, 'amount' => 360000, 'basis' => 'fttn', 'anchor' => 'last_unloading', 'offset_days' => 1, 'offset_unit' => 'bank_days'],
                ['percent' => 25, 'amount' => 1856500, 'basis' => 'fttn', 'anchor' => 'border_crossing', 'offset_days' => 5, 'offset_unit' => 'bank_days'],
            ],
        ];

        $normalized = PaymentInstallmentScheduleNormalizer::normalize($schedule, 4573000.0);
        $amounts = array_column($normalized['installments'], 'amount');

        $this->assertSame([500000.0, 1856500.0, 360000.0, 1856500.0], $amounts);
        $this->assertEqualsWithDelta(100.0, array_sum(array_column($normalized['installments'], 'percent')), 0.01);
    }

    #[Test]
    public function it_recalculates_amounts_from_percents_when_amounts_do_not_match_total(): void
    {
        $schedule = [
            'installments' => [
                ['percent' => 25, 'amount' => 1, 'basis' => 'fttn', 'anchor' => 'loading_date', 'offset_days' => 0, 'offset_unit' => 'calendar_days'],
                ['percent' => 25, 'amount' => 1, 'basis' => 'fttn', 'anchor' => 'loading_date', 'offset_days' => 0, 'offset_unit' => 'calendar_days'],
                ['percent' => 25, 'amount' => 1, 'basis' => 'fttn', 'anchor' => 'loading_date', 'offset_days' => 0, 'offset_unit' => 'calendar_days'],
                ['percent' => 25, 'amount' => 1, 'basis' => 'fttn', 'anchor' => 'loading_date', 'offset_days' => 0, 'offset_unit' => 'calendar_days'],
            ],
        ];

        $normalized = PaymentInstallmentScheduleNormalizer::normalize($schedule, 4573000.0);
        $amounts = array_column($normalized['installments'], 'amount');

        $this->assertSame([1143250.0, 1143250.0, 1143250.0, 1143250.0], $amounts);
    }
}
