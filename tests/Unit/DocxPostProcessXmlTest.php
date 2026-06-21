<?php

namespace Tests\Unit;

use App\Support\DocxHeaderFooterOverlayParagraphCompactor;
use App\Support\DocxOrphanSeparatorCleaner;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocxPostProcessXmlTest extends TestCase
{
    private const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    #[Test]
    public function orphan_cleaner_strips_leading_comma_before_static_text(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="'.self::W_NS.'"><w:body><w:p><w:r><w:t>, паспортные данные</w:t></w:r></w:p></w:body></w:document>';

        $out = DocxOrphanSeparatorCleaner::cleanWordprocessingMl($xml);

        $this->assertStringContainsString('<w:t>паспортные данные</w:t>', $out);
        $this->assertStringNotContainsString(', паспортные', $out);
    }

    #[Test]
    public function footer_overlay_paragraph_gets_tight_line_spacing(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:ftr xmlns:w="'.self::W_NS.'" xmlns:v="urn:schemas-microsoft-com:vml">'
            .'<w:p><w:r><w:pict><v:shape type="#_x0000_t75" style="width:10pt;height:10pt"/></w:pict></w:r></w:p>'
            .'</w:ftr>';

        $out = DocxHeaderFooterOverlayParagraphCompactor::patch($xml, 'word/footer1.xml');

        $this->assertStringContainsString('w:spacing', $out);
        $this->assertStringContainsString('w:lineRule="exact"', $out);
    }
}
