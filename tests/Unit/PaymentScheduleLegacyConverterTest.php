<?php

namespace Tests\Unit;

use App\Support\PaymentInstallmentScheduleNormalizer;
use App\Support\PaymentScheduleLegacyConverter;
use PHPUnit\Framework\TestCase;

class PaymentScheduleLegacyConverterTest extends TestCase
{
    public function test_converts_single_postpayment_to_one_installment(): void
    {
        $converted = PaymentScheduleLegacyConverter::toInstallments([
            'has_prepayment' => false,
            'postpayment_days' => 10,
            'postpayment_mode' => 'unloading',
        ]);

        $this->assertCount(1, $converted['installments']);
        $this->assertSame(100.0, $converted['installments'][0]['percent']);
        $this->assertSame(10, $converted['installments'][0]['offset_days']);
        $this->assertSame('unloading', $converted['installments'][0]['basis']);
        $this->assertSame('last_unloading', $converted['installments'][0]['anchor']);
    }

    public function test_converts_prepayment_and_postpayment_to_two_installments(): void
    {
        $converted = PaymentScheduleLegacyConverter::toInstallments([
            'has_prepayment' => true,
            'prepayment_ratio' => 30,
            'prepayment_days' => 3,
            'prepayment_mode' => 'fttn',
            'postpayment_days' => 14,
            'postpayment_mode' => 'ottn',
        ]);

        $this->assertCount(2, $converted['installments']);
        $this->assertSame(30.0, $converted['installments'][0]['percent']);
        $this->assertSame(70.0, $converted['installments'][1]['percent']);
    }

    public function test_ensure_installment_model_normalizes_three_installment_amounts(): void
    {
        $schedule = PaymentInstallmentScheduleNormalizer::normalize([
            'installments' => [
                ['percent' => 40, 'offset_days' => 0, 'basis' => 'fttn', 'anchor' => 'last_unloading'],
                ['percent' => 30, 'offset_days' => 5, 'basis' => 'ottn', 'anchor' => 'last_unloading'],
                ['percent' => 30, 'offset_days' => 10, 'basis' => 'unloading', 'anchor' => 'last_unloading'],
            ],
        ], 1000.0);

        $this->assertCount(3, $schedule['installments']);
        $this->assertSame(400.0, $schedule['installments'][0]['amount']);
        $this->assertSame(300.0, $schedule['installments'][1]['amount']);
        $this->assertSame(300.0, $schedule['installments'][2]['amount']);
    }
}
