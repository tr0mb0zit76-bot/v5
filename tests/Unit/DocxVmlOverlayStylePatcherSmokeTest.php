<?php

namespace Tests\Unit;

use App\Support\DocxVmlOverlayStylePatcher;
use PHPUnit\Framework\TestCase;

class DocxVmlOverlayStylePatcherSmokeTest extends TestCase
{
    public function test_patch_wordprocessing_ml_adds_margin_to_vml_shape(): void
    {
        $xml = '<w:document><w:p><v:shape type="#_x0000_t75" style="width:137px;height:60px" stroked="f" filled="f"><v:imagedata r:id="rId1" o:title=""/></v:shape></w:p></w:document>';
        $overlayStyles = [
            ['margin_left_mm' => 11.0, 'margin_top_mm' => -6.0],
        ];
        $idx = 0;
        $patched = DocxVmlOverlayStylePatcher::patchWordprocessingMl($xml, $overlayStyles, $idx);

        $this->assertStringContainsString('margin-left:11.00mm', $patched);
        $this->assertStringContainsString('margin-top:-6.00mm', $patched);
        $this->assertStringContainsString('mso-position-horizontal-relative:page', $patched);
    }

    public function test_patch_docx_writes_margins_into_minimal_zip(): void
    {
        $documentXml = '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:v="urn:schemas-microsoft-com:vml"><w:body><w:p><w:r><w:t></w:t><w:pict><v:shape type="#_x0000_t75" style="width:137px;height:60px" stroked="f" filled="f"><v:imagedata r:id="rId1" o:title=""/></v:shape></w:pict><w:t></w:t></w:r></w:p></w:body></w:document>';

        $path = tempnam(sys_get_temp_dir(), 'crm-docx-patch-');
        $this->assertNotFalse($path);
        @unlink($path);
        $path .= '.docx';

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        DocxVmlOverlayStylePatcher::patchDocx($path, [
            ['margin_left_mm' => 11.0, 'margin_top_mm' => -6.0],
        ]);

        $zip2 = new \ZipArchive;
        $this->assertTrue($zip2->open($path, 0));
        $out = $zip2->getFromName('word/document.xml');
        $zip2->close();
        @unlink($path);

        $this->assertIsString($out);
        $this->assertStringContainsString('margin-left:11.00mm', $out);
    }
}
