<?php

namespace Tests\Unit;

use App\Services\Commercial\ProposalEmlImportService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProposalEmlImportServiceTest extends TestCase
{
    #[Test]
    public function it_imports_html_and_cid_images_into_public_stock(): void
    {
        if (! Schema::hasTable('proposal_html_templates')) {
            $this->markTestSkipped('Нет таблицы proposal_html_templates.');
        }

        $png = base64_encode(hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        ));

        $eml = <<<EML
Subject: Demo
MIME-Version: 1.0
Content-Type: multipart/related; boundary="boundary-related"

--boundary-related
Content-Type: text/html; charset=utf-8

<html><head><style>.em-title{color:#111}</style><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto+Slab&display=swap"></head><body><p class="em-title">Привет, <strong style="color:#de3b3b;">МЕНЯЕМ_ИМЯ</strong>!</p><img src="cid:logo@emailmaker" width="10" height="10"></body></html>

--boundary-related
Content-Type: image/png
Content-Transfer-Encoding: base64
Content-ID: <logo@emailmaker>

{$png}
--boundary-related--
EML;

        $slug = 'logistic-teaser-test';
        $dir = public_path('assets/proposal-emails/'.$slug);
        File::deleteDirectory($dir);

        $result = app(ProposalEmlImportService::class)->importContents(
            $eml,
            'Логистические решения — тест',
            $slug,
        );

        $template = $result['template'];

        $this->assertSame(1, $result['assets_written']);
        $this->assertStringContainsString('/assets/proposal-emails/'.$slug.'/logo.png', $template->html_body);
        $this->assertStringContainsString('{counterparty.contact_person}', $template->html_body);
        $this->assertStringNotContainsString('cid:', $template->html_body);
        $this->assertStringNotContainsString('<html', strtolower($template->html_body));
        $this->assertStringNotContainsString('<head', strtolower($template->html_body));
        $this->assertStringContainsString('em-title', (string) $template->css_inline);
        $this->assertStringContainsString('fonts.googleapis.com', (string) $template->css_inline);
        $this->assertIsArray($template->email_assets);
        $this->assertCount(1, $template->email_assets);
        $this->assertFileExists(public_path(ltrim($template->email_assets[0]['public_path'], '/')));

        File::deleteDirectory($dir);
    }
}
