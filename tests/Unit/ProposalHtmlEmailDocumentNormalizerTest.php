<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ProposalHtmlEmailDocumentNormalizer;
use PHPUnit\Framework\TestCase;

class ProposalHtmlEmailDocumentNormalizerTest extends TestCase
{
    public function test_strips_document_shell_and_moves_styles_to_css(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
<style>.em-box{padding:10px}</style>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto+Slab&display=swap">
</head>
<body><div class="em-box">Привет</div></body>
</html>
HTML;

        $normalized = ProposalHtmlEmailDocumentNormalizer::normalize($html);

        $this->assertSame('<div class="em-box">Привет</div>', $normalized['body']);
        $this->assertStringContainsString('.em-box{padding:10px}', $normalized['css']);
        $this->assertStringContainsString('@import url(', $normalized['css']);
        $this->assertSame(
            ['https://fonts.googleapis.com/css2?family=Roboto+Slab&display=swap'],
            $normalized['font_urls'],
        );
    }

    public function test_keeps_fragment_without_body_tag(): void
    {
        $normalized = ProposalHtmlEmailDocumentNormalizer::normalize(
            '<table><tr><td>OK</td></tr></table>',
            'body{margin:0}',
        );

        $this->assertSame('<table><tr><td>OK</td></tr></table>', $normalized['body']);
        $this->assertStringContainsString('body{margin:0}', $normalized['css']);
    }
}
