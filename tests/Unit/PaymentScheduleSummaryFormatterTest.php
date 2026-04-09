<?php

namespace Tests\Unit;

use App\Support\PaymentScheduleSummaryFormatter;
use PHPUnit\Framework\TestCase;

class PaymentScheduleSummaryFormatterTest extends TestCase
{
    public function test_formats_postpayment_only_like_wizard(): void
    {
        $this->assertSame(
            '100% 5 дн ОТТН',
            PaymentScheduleSummaryFormatter::format([
                'has_prepayment' => false,
                'postpayment_days' => 5,
                'postpayment_mode' => 'ottn',
            ]),
        );
    }

    public function test_formats_prepayment_and_postpayment_like_wizard(): void
    {
        $this->assertSame(
            '30% 1 дн ФТТН / 70% 5 дн ОТТН',
            PaymentScheduleSummaryFormatter::format([
                'has_prepayment' => true,
                'prepayment_ratio' => 30,
                'prepayment_days' => 1,
                'prepayment_mode' => 'fttn',
                'postpayment_days' => 5,
                'postpayment_mode' => 'ottn',
            ]),
        );
    }

    public function test_basis_labels_match_wizard_options(): void
    {
        $this->assertSame(
            '100% 0 дн На выгрузке',
            PaymentScheduleSummaryFormatter::format([
                'has_prepayment' => false,
                'postpayment_days' => 0,
                'postpayment_mode' => 'unloading',
            ]),
        );
    }
}
