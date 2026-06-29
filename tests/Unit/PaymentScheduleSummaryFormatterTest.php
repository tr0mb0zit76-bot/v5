<?php

namespace Tests\Unit;

use App\Support\PaymentScheduleSummaryFormatter;
use PHPUnit\Framework\TestCase;

class PaymentScheduleSummaryFormatterTest extends TestCase
{
    public function test_formats_postpayment_only_like_wizard(): void
    {
        $this->assertSame(
            '100%, через 5 календ. дн после последней выгрузки, по оригиналам',
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
            '30%, через 1 календ. дн после последней выгрузки, по сканам; 70%, через 5 календ. дн после последней выгрузки, по оригиналам',
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
            '100%, в день якоря (последней выгрузки), при выгрузке',
            PaymentScheduleSummaryFormatter::format([
                'has_prepayment' => false,
                'postpayment_days' => 0,
                'postpayment_mode' => 'unloading',
            ]),
        );
    }
}
