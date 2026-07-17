<?php

namespace Tests\Unit\Support;

use App\Support\ProposalHtmlManagerContactNormalizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProposalHtmlManagerContactNormalizerTest extends TestCase
{
    #[Test]
    public function replaces_hardcoded_contacts_and_strips_gray_background(): void
    {
        $html = <<<'HTML'
<td><div class="em-font-RobotoSlab-Regular"><strong>Анатолий Шипицин</strong></div></td>
<td style="padding-top: 2px; padding-bottom: 10px; background-color: #e5e5e5;" bgcolor="#E5E5E5">
  <img width="18" height="18" alt="">
  <strong>+7 901 940 77 22&nbsp;<br></strong>
  <a href="mailto:sha@avtoaliyans.ru">sha@avtoaliyans.ru</a>
</td>
HTML;

        $normalized = ProposalHtmlManagerContactNormalizer::normalize($html);

        $this->assertStringContainsString('{manager.name}', $normalized);
        $this->assertStringContainsString('{manager.phone}', $normalized);
        $this->assertStringContainsString('mailto:{manager.email}', $normalized);
        $this->assertStringContainsString('>{manager.email}</a>', $normalized);
        $this->assertStringNotContainsString('Анатолий Шипицин', $normalized);
        $this->assertStringNotContainsString('+7 901 940 77 22', $normalized);
        $this->assertStringNotContainsString('sha@avtoaliyans.ru', $normalized);
        $this->assertStringNotContainsString('e5e5e5', strtolower($normalized));
        $this->assertStringNotContainsString('E5E5E5', $normalized);
    }

    #[Test]
    public function wraps_plain_manager_email_in_mailto_link(): void
    {
        $html = '<strong>{manager.phone}<br></strong> {manager.email}';

        $normalized = ProposalHtmlManagerContactNormalizer::normalize($html);

        $this->assertStringContainsString('href="mailto:{manager.email}"', $normalized);
        $this->assertStringContainsString('>{manager.email}</a>', $normalized);
        $this->assertSame(1, substr_count($normalized, 'mailto:{manager.email}'));
    }

    #[Test]
    public function inserts_mailto_when_email_placeholder_missing_after_phone(): void
    {
        $html = '<strong>{manager.name}</strong><strong>{manager.phone}&nbsp;<br/></strong><span id="empty"></span>';

        $normalized = ProposalHtmlManagerContactNormalizer::normalize($html);

        $this->assertStringContainsString('mailto:{manager.email}', $normalized);
        $this->assertStringContainsString('{manager.email}</a>', $normalized);
    }

    #[Test]
    public function replaces_emil_and_lilia_variants(): void
    {
        $html = '<strong>Лилия Рашитова</strong>+7&nbsp;917 030-04-59<a href="mailto:l.rashitova@log-sol.ru ">l.rashitova@log-sol.ru </a>'
            .'<strong>Эмиль Садыков</strong>+7&nbsp;917 141 70 07<a href="mailto:sad@log-sol.ru">sad@log-sol.ru</a>';

        $normalized = ProposalHtmlManagerContactNormalizer::normalize($html);

        $this->assertSame(2, substr_count($normalized, '{manager.name}'));
        $this->assertSame(2, substr_count($normalized, '{manager.phone}'));
        $this->assertGreaterThanOrEqual(2, substr_count($normalized, 'mailto:{manager.email}'));
    }

    #[Test]
    public function inserts_mailto_for_parallel_import_span_phone_layout(): void
    {
        $html = '<strong id="iakm4"><span id="i2l6km">{manager.phone}</span> <br/></strong> <span id="empty"></span>';

        $normalized = ProposalHtmlManagerContactNormalizer::normalize($html);

        $this->assertStringContainsString('mailto:{manager.email}', $normalized);
        $this->assertStringContainsString('{manager.email}</a>', $normalized);
    }
}
