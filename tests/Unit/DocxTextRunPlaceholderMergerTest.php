<?php

namespace Tests\Unit;

use App\Support\DocxTextRunPlaceholderMerger;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocxTextRunPlaceholderMergerTest extends TestCase
{
    #[Test]
    public function it_merges_five_part_split_macro_inside_paragraph(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p>
<w:r><w:t xml:space="preserve">${</w:t></w:r>
<w:r><w:t>cp</w:t></w:r>
<w:r><w:t>_</w:t></w:r>
<w:r><w:t>KPP</w:t></w:r>
<w:r><w:t>}</w:t></w:r>
</w:p></w:body></w:document>';

        $merged = DocxTextRunPlaceholderMerger::mergeAllSplitDollarMacrosInXml($xml);

        $this->assertStringContainsString('${cp_KPP}', $merged);
        $this->assertStringNotContainsString('<w:t>cp</w:t></w:r><w:r><w:t>_</w:t>', $merged);

        $dom = new \DOMDocument;
        $this->assertTrue(@$dom->loadXML($merged, LIBXML_NONET | LIBXML_COMPACT));
    }

    #[Test]
    public function it_keeps_intact_macro_and_following_text_separate(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p>
<w:r><w:t xml:space="preserve"> ${lp_rs}</w:t></w:r>
<w:r><w:t> Банк</w:t></w:r>
</w:p></w:body></w:document>';

        $merged = DocxTextRunPlaceholderMerger::mergeAllSplitDollarMacrosInXml($xml);

        $this->assertStringContainsString('${lp_rs}', $merged);
        $this->assertStringContainsString('> Банк</w:t>', $merged);
    }
}
