<?php

namespace Tests\Unit;

use App\Models\PrintFormBasicTerm;
use App\Support\PrintFormBasicTermsTableCloner;
use App\Support\PrintFormTemplateProcessorPreparer;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\TemplateProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class PrintFormBasicTermsTableClonerTest extends TestCase
{
    #[DataProvider('basicTermsPlaceholderProvider')]
    public function test_is_basic_terms_placeholder(string $placeholder, bool $expected): void
    {
        $this->assertSame($expected, PrintFormBasicTermsTableCloner::isBasicTermsPlaceholder($placeholder));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function basicTermsPlaceholderProvider(): array
    {
        return [
            'customer index' => ['cp_basic_terms_row_index', true],
            'customer text anchor' => ['cp_basic_terms_row_text', true],
            'carrier text anchor' => ['dp_basic_terms_row_text', true],
            'cloned customer row' => ['cp_basic_terms_row_text#2', true],
            'unrelated cargo macro' => ['cargo_row_name', false],
        ];
    }

    public function test_for_party_returns_prefix_specific_cloner(): void
    {
        $customer = PrintFormBasicTermsTableCloner::forParty(PrintFormBasicTerm::PARTY_CUSTOMER);
        $carrier = PrintFormBasicTermsTableCloner::forParty(PrintFormBasicTerm::PARTY_CARRIER);

        $this->assertNotNull($customer);
        $this->assertNotNull($carrier);
        $this->assertSame('cp_basic_terms_row_text', $customer->cloneRowAnchor());
        $this->assertSame('dp_basic_terms_row_text', $carrier->cloneRowAnchor());
        $this->assertNull(PrintFormBasicTermsTableCloner::forParty('internal'));
    }

    public function test_parties_from_placeholders_detects_customer_and_carrier(): void
    {
        $parties = PrintFormBasicTermsTableCloner::partiesFromPlaceholders([
            'order.number',
            'dp_basic_terms_row_text',
            'cp_basic_terms_row_index',
            'cargo_row_name',
        ]);

        $this->assertSame([
            PrintFormBasicTerm::PARTY_CARRIER,
            PrintFormBasicTerm::PARTY_CUSTOMER,
        ], $parties);
    }

    #[Test]
    public function it_clones_carrier_basic_terms_rows(): void
    {
        $templatePath = $this->createBasicTermsTemplate();
        $outputPath = $templatePath.'-out.docx';
        $cloner = PrintFormBasicTermsTableCloner::forParty(PrintFormBasicTerm::PARTY_CARRIER);

        $this->assertNotNull($cloner);

        try {
            $processor = new TemplateProcessor($templatePath);
            $processor->setMacroChars('${', '}');
            PrintFormTemplateProcessorPreparer::repairCloneRowMacros($processor, $cloner->rowMacroNames());
            $this->assertTrue($cloner->templateHasTermsTable($processor));

            $cloner->apply($processor, [
                [
                    'dp_basic_terms_row_index' => '1',
                    'dp_basic_terms_row_text' => 'Пункт один',
                ],
                [
                    'dp_basic_terms_row_index' => '2',
                    'dp_basic_terms_row_text' => 'Пункт два',
                ],
            ]);

            $processor->saveAs($outputPath);

            $plain = $this->documentPlainText($outputPath);

            $this->assertStringContainsString('Пункт один', $plain);
            $this->assertStringContainsString('Пункт два', $plain);
        } finally {
            foreach ([$templatePath, $outputPath] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }

    #[Test]
    public function it_clones_rows_after_repairing_split_placeholder_runs(): void
    {
        $templatePath = $this->createBasicTermsTemplate();
        $this->splitPlaceholderInDocumentXml($templatePath, 'dp_basic_terms_row_text');
        $this->splitPlaceholderInDocumentXml($templatePath, 'dp_basic_terms_row_index');

        $outputPath = $templatePath.'-out.docx';
        $cloner = PrintFormBasicTermsTableCloner::forParty(PrintFormBasicTerm::PARTY_CARRIER);

        $this->assertNotNull($cloner);

        try {
            $processor = new TemplateProcessor($templatePath);
            $processor->setMacroChars('${', '}');
            PrintFormTemplateProcessorPreparer::repairCloneRowMacros($processor, $cloner->rowMacroNames());
            $this->assertTrue($cloner->templateHasTermsTable($processor));

            $cloner->apply($processor, [
                [
                    'dp_basic_terms_row_index' => '1',
                    'dp_basic_terms_row_text' => 'После склейки',
                ],
            ]);

            $processor->saveAs($outputPath);

            $this->assertStringContainsString('После склейки', $this->documentPlainText($outputPath));
        } finally {
            foreach ([$templatePath, $outputPath] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }

    private function createBasicTermsTemplate(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'crm-basic-terms-tpl-');
        $this->assertNotFalse($tmp);
        @unlink($tmp);
        $path = $tmp.'.docx';

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(900)->addText('${dp_basic_terms_row_index}');
        $table->addCell(4500)->addText('${dp_basic_terms_row_text}');

        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    private function splitPlaceholderInDocumentXml(string $docxPath, string $inner): void
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($docxPath));
        $xml = (string) $zip->getFromName('word/document.xml');
        $full = '${'.$inner.'}';
        $split = '<w:t>$</w:t></w:r><w:r><w:t>{'.$inner.'}</w:t>';
        $xml = str_replace('<w:t>'.$full.'</w:t>', $split, $xml);
        $zip->addFromString('word/document.xml', $xml);
        $zip->close();
    }

    private function documentPlainText(string $docxPath): string
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($docxPath));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        $this->assertIsString($xml);

        return html_entity_decode(strip_tags(preg_replace('/<[^>]+>/u', '', $xml) ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
