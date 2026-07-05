<?php

namespace Tests\Unit\Support;

use App\Support\ProposalHtmlTemplateColdEmailLibrary;
use PHPUnit\Framework\TestCase;

class ProposalHtmlTemplateColdEmailLibraryTest extends TestCase
{
    public function test_library_contains_expanded_cold_email_pack(): void
    {
        $templates = ProposalHtmlTemplateColdEmailLibrary::templates();

        $this->assertCount(10, $templates);
        $this->assertContains('parallel-import-demo', array_column($templates, 'slug'));
        $this->assertContains('aa-chemical-logistics-cold', array_column($templates, 'slug'));
        $this->assertContains('aa-heavy-equipment-cold', array_column($templates, 'slug'));
        $this->assertContains('aa-proposal-follow-up', array_column($templates, 'slug'));
    }

    public function test_templates_use_autoyans_brand_sender_placeholders_and_local_assets(): void
    {
        foreach (ProposalHtmlTemplateColdEmailLibrary::templates() as $template) {
            $html = $template['html_body'];

            $this->assertStringContainsString('Автоальянс-Смоленск', $html, $template['slug']);
            $this->assertStringContainsString('{counterparty.contact_person}', $html, $template['slug']);
            $this->assertStringContainsString('{responsible.name}', $html, $template['slug']);
            $this->assertStringContainsString('{responsible.phone}', $html, $template['slug']);
            $this->assertStringContainsString('{responsible.email}', $html, $template['slug']);
            $this->assertStringContainsString('/assets/proposal-emails/', $html, $template['slug']);
            $this->assertStringNotContainsString('Логистические решения', $html, $template['slug']);
            $this->assertStringNotContainsString('img.hiteml.com', $html, $template['slug']);
            $this->assertStringNotContainsString('МЕНЯЕМ_ИМЯ', $html, $template['slug']);
        }
    }

    public function test_subjects_avoid_obvious_spam_triggers(): void
    {
        $spamTriggerPattern = '/(бесплатно|гарант|срочно|!!!|скидк)/iu';

        foreach (ProposalHtmlTemplateColdEmailLibrary::templates() as $template) {
            $this->assertDoesNotMatchRegularExpression($spamTriggerPattern, $template['subject'], $template['slug']);
            $this->assertLessThanOrEqual(70, mb_strlen($template['subject']), $template['slug']);
            $this->assertLessThanOrEqual(140, mb_strlen($template['preheader']), $template['slug']);
        }
    }
}
