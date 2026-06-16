<?php

namespace Tests\Unit;

use App\Services\OrderPrintFormDraftService;
use App\Support\DocxPrintFormPlaceholderPreprocessor;
use App\Support\OrderPrintFormContext;
use PhpOffice\PhpWord\TemplateProcessor;
use Tests\TestCase;
use ZipArchive;

class OrderPrintFormDraftServiceVerificationQrTest extends TestCase
{
    public function test_is_verification_qr_placeholder_matches_clone_suffixes(): void
    {
        $this->assertTrue(OrderPrintFormDraftService::isVerificationQrPlaceholder('document_verification_qr'));
        $this->assertTrue(OrderPrintFormDraftService::isVerificationQrPlaceholder('document_verification_qr#2'));
        $this->assertFalse(OrderPrintFormDraftService::isVerificationQrPlaceholder('document_verification_code'));
    }

    public function test_injects_qr_even_when_placeholder_not_listed_in_template_settings(): void
    {
        $templatePath = $this->makeDocxWithSplitQrPlaceholder();
        $outputPath = $templatePath.'.out.docx';

        try {
            DocxPrintFormPlaceholderPreprocessor::preprocess($templatePath, ['document_verification_qr']);

            $processor = new TemplateProcessor($templatePath);
            $processor->setMacroChars('${', '}');

            $service = app(OrderPrintFormDraftService::class);
            $method = new \ReflectionMethod($service, 'injectVerificationQrImage');
            $method->setAccessible(true);

            $tempFiles = $method->invoke(
                $service,
                $processor,
                collect(['order.number']),
                new OrderPrintFormContext(
                    documentVerificationCode: 'ABCDEF0123456789',
                    orderDocumentId: 42,
                ),
            );

            $this->assertNotSame([], $tempFiles);
            $processor->saveAs($outputPath);

            $xml = $this->readZipPart($outputPath, 'word/document.xml');
            $this->assertStringNotContainsString('${document_verification_qr}', $xml);
            $this->assertMatchesRegularExpression('/(v:imagedata|w:drawing|a:blip)/i', $xml);
        } finally {
            foreach ([$templatePath, $outputPath] as $file) {
                if (is_string($file) && is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }

    private function makeDocxWithSplitQrPlaceholder(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'crm-qr-docx-');
        $this->assertNotFalse($path);
        @unlink($path);
        $docxPath = $path.'.docx';

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($docxPath, ZipArchive::CREATE));

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p>
<w:r><w:t xml:space="preserve">${</w:t></w:r><w:r><w:t>document_verification_qr</w:t></w:r><w:r><w:t>}</w:t></w:r>
</w:p></w:body></w:document>');

        $zip->close();

        return $docxPath;
    }

    private function readZipPart(string $docxPath, string $part): string
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($docxPath));
        $contents = $zip->getFromName($part);
        $zip->close();

        return is_string($contents) ? $contents : '';
    }
}
