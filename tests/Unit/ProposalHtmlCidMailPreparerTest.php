<?php

namespace Tests\Unit;

use App\Services\Commercial\ProposalHtmlCidMailPreparer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProposalHtmlCidMailPreparerTest extends TestCase
{
    #[Test]
    public function it_rewrites_public_paths_to_cid_and_lists_embeds(): void
    {
        $slug = 'cid-prep-test';
        $relative = 'assets/proposal-emails/'.$slug.'/dot.png';
        $absolute = public_path($relative);
        if (! is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0777, true);
        }

        $png = hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        );
        file_put_contents($absolute, $png);

        $html = '<img src="/'.$relative.'" alt="x">';
        $assets = [[
            'cid' => 'dot.png',
            'public_path' => '/'.$relative,
            'relative_path' => $relative,
            'mime' => 'image/png',
            'filename' => 'dot.png',
        ]];

        $prepared = app(ProposalHtmlCidMailPreparer::class)->prepare($html, $assets);

        $this->assertSame('<img src="cid:dot.png" alt="x">', $prepared['html']);
        $this->assertCount(1, $prepared['embeds']);
        $this->assertSame($absolute, $prepared['embeds'][0]['path']);
        $this->assertSame('dot.png', $prepared['embeds'][0]['cid']);

        $inlined = app(ProposalHtmlCidMailPreparer::class)->inlineAsDataUris($html, $assets);
        $this->assertStringStartsWith('<img src="data:image/png;base64,', $inlined);
        $this->assertStringNotContainsString('/assets/proposal-emails/', $inlined);

        @unlink($absolute);
        @rmdir(dirname($absolute));
    }
}
