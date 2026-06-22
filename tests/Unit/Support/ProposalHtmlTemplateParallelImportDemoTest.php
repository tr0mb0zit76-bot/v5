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
        $this->assertStringContainsString('{route.loading_first_city}', $html);
        $this->assertStringContainsString('{offer.price}', $html);
        $this->assertStringContainsString('Параллельный импорт', $html);
        $this->assertStringContainsString('Почему выбирают нас?', $html);
    }

    public function test_demo_template_has_inline_css(): void
    {
        $css = ProposalHtmlTemplateParallelImportDemo::cssInline();

        $this->assertStringContainsString('background-color', $css);
        $this->assertStringContainsString('font-family', $css);
    }
}
