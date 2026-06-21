<?php

namespace Tests\Unit\Support\MailSync;

use App\Support\MailSync\MailMimeBodyExtractor;
use App\Support\OrderIntakeSchema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailMimeBodyExtractorTest extends TestCase
{
    #[Test]
    public function it_decodes_base64_windows_1251_body(): void
    {
        $plain = 'Привет, это тестовое письмо.';
        $encoded = base64_encode(mb_convert_encoding($plain, 'Windows-1251', 'UTF-8'));

        $decoded = MailMimeBodyExtractor::decodeContent($encoded, 3, 'windows-1251');

        $this->assertStringContainsString('Привет', $decoded);
        $this->assertStringContainsString('письмо', $decoded);
    }

    #[Test]
    public function it_decodes_quoted_printable_utf8_body(): void
    {
        $encoded = '=D0=9F=D1=80=D0=B8=D0=B2=D0=B5=D1=82';

        $decoded = MailMimeBodyExtractor::decodeContent($encoded, 4, 'utf-8');

        $this->assertSame('Привет', $decoded);
    }

    #[Test]
    public function order_intake_schema_maps_carrier_and_payment_terms(): void
    {
        $patch = OrderIntakeSchema::toWizardPatch([
            'customer' => [],
            'route' => [
                'loading' => ['address' => 'Пушкино'],
                'unloading' => ['address' => 'Подольск'],
            ],
            'cargo' => ['name' => 'дрова'],
            'commercial' => [
                'customer_rate' => 100000,
                'customer_payment_terms' => '5 банковских дней после выгрузки, НДС 22%',
                'carrier_rate' => 40000,
                'carrier_payment_terms' => 'наличные через месяц',
            ],
            'notes' => null,
            'confidence' => 0.9,
            'field_confidence' => [],
        ]);

        $this->assertSame(100000.0, $patch['patch']['financial_term']['client_price']);
        $this->assertSame(40000.0, $patch['patch']['financial_term']['contractors_costs'][0]['amount']);
        $this->assertStringContainsString('банковских', $patch['patch']['special_notes']);
    }
}
