<?php

namespace Tests\Unit;

use App\Support\DocxPrintFormPlaceholderPreprocessor;
use PhpOffice\PhpWord\TemplateProcessor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class DocxPrintFormPlaceholderPreprocessorTest extends TestCase
{
    #[Test]
    public function it_merges_split_header_placeholder_before_phpword_loads_template(): void
    {
        $sourcePath = $this->makeDocx([
            'word/document.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Body</w:t></w:r></w:body></w:document>',
            'word/header1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t xml:space="preserve">${</w:t></w:r><w:r><w:t>order_number</w:t></w:r><w:r><w:t>}</w:t></w:r></w:p></w:hdr>',
        ]);

        $workingPath = $this->copyDocx($sourcePath);

        DocxPrintFormPlaceholderPreprocessor::preprocess($workingPath, ['order_number']);

        $headerBeforeReplace = $this->readZipPart($workingPath, 'word/header1.xml');
        $this->assertStringContainsString('${order_number}', $headerBeforeReplace);

        $processor = new TemplateProcessor($workingPath);
        $processor->setMacroChars('${', '}');
        $processor->setValue('order_number', 'ORD-HEADER-99');

        $outputPath = $workingPath.'.out.docx';
        $processor->saveAs($outputPath);

        $headerAfterReplace = $this->readZipPart($outputPath, 'word/header1.xml');
        $this->assertStringContainsString('ORD-HEADER-99', $headerAfterReplace);
        $this->assertStringNotContainsString('${order_number}', $headerAfterReplace);

        $dom = new \DOMDocument;
        $this->assertTrue(@$dom->loadXML($headerAfterReplace, LIBXML_NONET | LIBXML_COMPACT));

        @unlink($sourcePath);
        @unlink($workingPath);
        @unlink($outputPath);
    }

    #[Test]
    public function it_merges_deeply_split_document_placeholder_before_substitution(): void
    {
        $sourcePath = $this->makeDocx([
            'word/document.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p>
<w:r><w:t xml:space="preserve">${</w:t></w:r><w:r><w:t>cp</w:t></w:r><w:r><w:t>_</w:t></w:r><w:r><w:t>inn</w:t></w:r><w:r><w:t>}</w:t></w:r>
</w:p></w:body></w:document>',
        ]);

        $workingPath = $this->copyDocx($sourcePath);
        DocxPrintFormPlaceholderPreprocessor::preprocess($workingPath, ['cp_inn']);

        $processor = new TemplateProcessor($workingPath);
        $processor->setMacroChars('${', '}');
        $this->assertContains('cp_inn', $processor->getVariables());

        $processor->setValue('cp_inn', '7701234567');
        $outputPath = $workingPath.'.out.docx';
        $processor->saveAs($outputPath);

        $documentAfterReplace = $this->readZipPart($outputPath, 'word/document.xml');
        $this->assertStringContainsString('7701234567', $documentAfterReplace);
        $this->assertStringNotContainsString('${cp_inn}', $documentAfterReplace);

        @unlink($sourcePath);
        @unlink($workingPath);
        @unlink($outputPath);
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function makeDocx(array $entries): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('crm-docx-src-', true).'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return $path;
    }

    private function copyDocx(string $sourcePath): string
    {
        $target = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('crm-docx-work-', true).'.docx';
        copy($sourcePath, $target);

        return $target;
    }

    private function readZipPart(string $docxPath, string $partName): string
    {
        $zip = new ZipArchive;
        $zip->open($docxPath);
        $contents = $zip->getFromName($partName);
        $zip->close();

        return is_string($contents) ? $contents : '';
    }
}
