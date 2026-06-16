<?php

namespace Tests\Unit;

use App\Support\BusinessProcessPlaybook;
use PHPUnit\Framework\TestCase;

class BusinessProcessPlaybookTest extends TestCase
{
    public function test_to_plain_text_strips_markdown_for_tasks(): void
    {
        $markdown = "## Шаги\n\n- [ ] Позвонить {lead_number}\n\n**Важно:** уточнить срок.";

        $plain = BusinessProcessPlaybook::toPlainText($markdown);

        $this->assertStringContainsString('Позвонить {lead_number}', $plain);
        $this->assertStringContainsString('Важно:', $plain);
        $this->assertStringNotContainsString('##', $plain);
        $this->assertStringNotContainsString('[ ]', $plain);
    }

    public function test_normalize_trims_and_nullifies_empty(): void
    {
        $this->assertNull(BusinessProcessPlaybook::normalize('   '));
        $this->assertSame('Текст', BusinessProcessPlaybook::normalize("  Текст\n"));
    }

    public function test_placeholder_catalog_includes_lead_tokens(): void
    {
        $tokens = BusinessProcessPlaybook::placeholderTokens();

        $this->assertContains('{lead_number}', $tokens);
        $this->assertContains('{stage_name}', $tokens);
    }
}
