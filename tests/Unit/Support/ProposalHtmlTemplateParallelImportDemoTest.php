<?php

namespace Tests\Unit\Support;

use App\Support\ProposalHtmlTemplateParallelImportDemo;
use PHPUnit\Framework\TestCase;

class ProposalHtmlTemplateParallelImportDemoTest extends TestCase
{
    public function test_demo_template_contains_lead_placeholders(): void
    {
        $html = ProposalHtmlTemplateParallelImportDemo::htmlBody();

        $this->assertStringContainsString('{counterparty.contact_person}', $html);
        $this->assertStringContainsString('{responsible.name}', $html);
        $this->assertStringContainsString('{responsible.phone}', $html);
        $this->assertStringContainsString('{responsible.email}', $html);
        $this->assertStringContainsString('Автоальянс-Смоленск', $html);
        $this->assertStringContainsString('Параллельный импорт', $html);
        $this->assertStringNotContainsString('Логистические решения', $html);
        $this->assertStringNotContainsString('img.hiteml.com', $html);
        $this->assertStringNotContainsString('МЕНЯЕМ_ИМЯ', $html);
    }

    public function test_demo_template_has_inline_css(): void
    {
        $css = ProposalHtmlTemplateParallelImportDemo::cssInline();

        $this->assertStringContainsString('background-color', $css);
        $this->assertStringContainsString('font-family', $css);
    }
}
