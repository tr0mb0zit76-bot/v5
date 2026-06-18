<?php

namespace Tests\Unit;

use App\Services\ImportCost\AltaSpravkaApiClient;
use App\Services\ImportCost\AltaSpravkaResponseParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AltaSpravkaResponseParserTest extends TestCase
{
    #[Test]
    public function test_parses_duty_and_vat_from_goodinfo_xml(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/alta-8429529000.xml'));
        $this->assertNotFalse($xml);

        $parsed = app(AltaSpravkaResponseParser::class)->parse($xml);

        $this->assertNotNull($parsed);
        $this->assertSame('8429529000', $parsed['code']);
        $this->assertSame('Прочие машины полноповоротные', $parsed['label']);
        $this->assertSame(5.0, $parsed['duty_percent']);
        $this->assertSame(22.0, $parsed['vat_percent']);
        $this->assertNull($parsed['error_code']);
    }

    #[Test]
    public function test_parses_auth_error_xml(): void
    {
        $parsed = app(AltaSpravkaResponseParser::class)->parse(
            '<?xml version="1.0" encoding="utf-8"?><Error><ErrorCode>101</ErrorCode><ErrorDescr>Некорректные данные для авторизации</ErrorDescr></Error>'
        );

        $this->assertNotNull($parsed);
        $this->assertSame('101', $parsed['error_code']);
        $this->assertSame('Некорректные данные для авторизации', $parsed['error_message']);
        $this->assertNull($parsed['duty_percent']);
    }

    #[Test]
    public function test_build_secret_matches_alta_sample_algorithm(): void
    {
        config([
            'import_cost_calculator.alta.login' => 'testlogin',
            'import_cost_calculator.alta.password' => 'testpassword',
        ]);

        $client = app(AltaSpravkaApiClient::class);

        $this->assertSame(
            md5('0101291000:testlogin:'.md5('testpassword')),
            $client->buildSecret('0101291000')
        );
    }
}
