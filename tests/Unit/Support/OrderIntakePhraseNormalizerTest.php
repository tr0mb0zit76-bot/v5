<?php

namespace Tests\Unit\Support;

use App\Support\OrderIntakePhraseNormalizer;
use Tests\TestCase;

class OrderIntakePhraseNormalizerTest extends TestCase
{
    public function test_normalizes_payment_through_month_phrase(): void
    {
        $this->assertSame(
            '30 календарных дней',
            OrderIntakePhraseNormalizer::normalizePaymentTermsText('оплата через месяц'),
        );
    }

    public function test_normalizes_instruction_before_llm(): void
    {
        $text = 'наша компания Автоальянс, оплата через месяц после выгрузки';

        $normalized = OrderIntakePhraseNormalizer::normalizeInstruction($text);

        $this->assertStringContainsString('30 календарных дней', $normalized);
    }
}
