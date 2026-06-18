<?php

namespace Tests\Unit;

use App\Services\ImportCost\KodTnVedPageParser;
use Tests\TestCase;

class KodTnVedPageParserTest extends TestCase
{
    public function test_parses_import_duty_vat_and_label_from_kodtnved_html(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/kodtnved-8429529000.html'));
        $this->assertNotFalse($html);

        $parsed = app(KodTnVedPageParser::class)->parse($html);

        $this->assertNotNull($parsed);
        $this->assertSame('ПРОЧИЕ МАШИНЫ ПОЛНОВОРОТНЫЕ', $parsed['label']);
        $this->assertSame(5.0, $parsed['duty_percent']);
        $this->assertSame(20.0, $parsed['vat_percent']);
    }
}
