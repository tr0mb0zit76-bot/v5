<?php

namespace Tests\Unit;

use App\Support\DocxVmlOverlayStylePatcher;
use Tests\TestCase;

class DocxVmlOverlayStylePatcherTest extends TestCase
{
    public function test_skips_overlay_offsets_for_qr_shapes_before_signature_and_stamp(): void
    {
        $xml = <<<'XML'
<v:shape type="#_x0000_t75" style="width:20pt;height:20pt;">
<v:shape type="#_x0000_t75" style="width:40pt;height:20pt;">
<v:shape type="#_x0000_t75" style="width:30pt;height:30pt;">
XML;

        $overlayIdx = 0;
        $remainingSkips = 1;
        $overlayStyles = [
            ['margin_left_mm' => 11.5, 'margin_top_mm' => 22.0],
            ['margin_left_mm' => 33.0, 'margin_top_mm' => 44.0],
        ];

        $patched = DocxVmlOverlayStylePatcher::patchWordprocessingMl(
            $xml,
            $overlayStyles,
            $overlayIdx,
            'word/document.xml',
            $remainingSkips,
        );

        $this->assertSame(0, $remainingSkips);
        $this->assertSame(2, $overlayIdx);
        $this->assertStringContainsString('margin-left:11.50mm;margin-top:22.00mm', $patched);
        $this->assertStringContainsString('margin-left:33.00mm;margin-top:44.00mm', $patched);
        $this->assertStringNotContainsString('margin-left:0.00mm', $patched);
        $this->assertSame(2, substr_count($patched, 'margin-left:'));
    }
}
