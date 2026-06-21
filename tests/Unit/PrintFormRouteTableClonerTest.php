<?php

namespace Tests\Unit;

use App\Support\PrintFormRouteTableCloner;
use PhpOffice\PhpWord\TemplateProcessor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class PrintFormRouteTableClonerTest extends TestCase
{
    #[Test]
    public function it_detects_route_table_placeholders(): void
    {
        $this->assertTrue(PrintFormRouteTableCloner::isRouteTablePlaceholder('route_row_stage'));
        $this->assertTrue(PrintFormRouteTableCloner::isRouteTablePlaceholder('route_row_summary#2'));
        $this->assertFalse(PrintFormRouteTableCloner::isRouteTablePlaceholder('route.loading_cities'));
    }

    #[Test]
    public function it_clones_table_rows_for_each_route_leg(): void
    {
        $templatePath = $this->createRouteTableTemplate();
        $outputPath = $templatePath.'-out.docx';

        try {
            $processor = new TemplateProcessor($templatePath);
            $processor->setMacroChars('${', '}');

            (new PrintFormRouteTableCloner)->apply($processor, [
                [
                    'route_row_index' => '1',
                    'route_row_stage' => 'Плечо 1',
                    'route_row_loading_addresses' => 'Москва',
                    'route_row_unloading_addresses' => 'Тула',
                    'route_row_loading_cities' => 'Москва',
                    'route_row_unloading_cities' => 'Тула',
                    'route_row_summary' => 'Плечо 1: Москва → Тула',
                ],
                [
                    'route_row_index' => '2',
                    'route_row_stage' => 'Плечо 2',
                    'route_row_loading_addresses' => 'Тула',
                    'route_row_unloading_addresses' => 'Воронеж',
                    'route_row_loading_cities' => 'Тула',
                    'route_row_unloading_cities' => 'Воронеж',
                    'route_row_summary' => 'Плечо 2: Тула → Воронеж',
                ],
            ]);

            $processor->saveAs($outputPath);

            $plain = $this->documentPlainText($outputPath);
            $this->assertStringContainsString('Плечо 1', $plain);
            $this->assertStringContainsString('Плечо 2', $plain);
            $this->assertStringContainsString('Воронеж', $plain);
        } finally {
            @unlink($templatePath);
            @unlink($outputPath);
        }
    }

    private function createRouteTableTemplate(): string
    {
        $path = sys_get_temp_dir().'/route-table-'.uniqid('', true).'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl><w:tr><w:tc><w:p><w:r><w:t>${route_row_stage}</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>${route_row_summary}</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:body></w:document>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>');
        $zip->close();

        return $path;
    }

    private function documentPlainText(string $path): string
    {
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return strip_tags((string) $xml);
    }
}
