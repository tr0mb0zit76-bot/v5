<?php

namespace Tests\Unit;

use App\Support\PrintFormCargoTableCloner;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\TemplateProcessor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class PrintFormCargoTableClonerTest extends TestCase
{
    #[Test]
    public function it_detects_cargo_table_placeholders(): void
    {
        $this->assertTrue(PrintFormCargoTableCloner::isCargoTablePlaceholder('cargo_row_name'));
        $this->assertTrue(PrintFormCargoTableCloner::isCargoTablePlaceholder('cargo_row_weight#2'));
        $this->assertFalse(PrintFormCargoTableCloner::isCargoTablePlaceholder('cargo.summary'));
    }

    #[Test]
    public function it_clones_table_rows_for_each_cargo_item(): void
    {
        $templatePath = $this->createCargoTableTemplate();
        $outputPath = $templatePath.'-out.docx';

        try {
            $processor = new TemplateProcessor($templatePath);
            $processor->setMacroChars('${', '}');

            (new PrintFormCargoTableCloner)->apply($processor, [
                [
                    'cargo_row_index' => '1',
                    'cargo_row_name' => 'Паллеты А',
                    'cargo_row_summary' => 'Паллеты А, 1000 кг',
                    'cargo_row_text' => "Паллеты А\nВес: 1000 кг",
                    'cargo_row_weight' => '1000 кг',
                    'cargo_row_volume' => '12 м³',
                    'cargo_row_packages' => '10',
                    'cargo_row_hs_code' => '1234',
                    'cargo_row_dimensions' => '1×1×1 м',
                ],
                [
                    'cargo_row_index' => '2',
                    'cargo_row_name' => 'Короба Б',
                    'cargo_row_summary' => 'Короба Б, 500 кг',
                    'cargo_row_text' => 'Короба Б',
                    'cargo_row_weight' => '500 кг',
                    'cargo_row_volume' => '',
                    'cargo_row_packages' => '5',
                    'cargo_row_hs_code' => '',
                    'cargo_row_dimensions' => '',
                ],
            ]);

            $processor->saveAs($outputPath);

            $plain = $this->documentPlainText($outputPath);

            $this->assertStringContainsString('Паллеты А', $plain);
            $this->assertStringContainsString('Короба Б', $plain);
            $this->assertStringContainsString('1000 кг', $plain);
            $this->assertStringContainsString('500 кг', $plain);
        } finally {
            foreach ([$templatePath, $outputPath] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }

    private function createCargoTableTemplate(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'crm-cargo-tpl-');
        $this->assertNotFalse($tmp);
        @unlink($tmp);
        $path = $tmp.'.docx';

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(1200)->addText('${cargo_row_index}');
        $table->addCell(3500)->addText('${cargo_row_name}');
        $table->addCell(2500)->addText('${cargo_row_weight}');

        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
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
