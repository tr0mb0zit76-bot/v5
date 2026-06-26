<?php

namespace Tests\Unit;

use App\Models\PrintFormTemplate;
use App\Services\DocxPlaceholderExtractor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocxPlaceholderExtractorTest extends TestCase
{
    #[Test]
    public function it_merges_stored_and_extracted_placeholder_lists(): void
    {
        $extractor = new DocxPlaceholderExtractor;

        $merged = $extractor->mergePlaceholderLists(
            ['dp_podpisant', 'custom_x'],
            ['dp_FIO_podpisant_im', 'custom_x'],
        );

        $this->assertSame(['custom_x', 'dp_FIO_podpisant_im', 'dp_podpisant'], $merged);
    }

    #[Test]
    public function it_merges_docx_placeholders_with_stale_settings_variables(): void
    {
        $docxPath = base_path('public/change/Шаблоны/Заявка с перевозчиком ВЭД.docx');
        if (! is_file($docxPath)) {
            $this->markTestSkipped('Carrier VED template docx is unavailable.');
        }

        $extractor = new DocxPlaceholderExtractor;
        $template = new PrintFormTemplate([
            'file_disk' => 'local',
            'file_path' => 'missing-on-purpose.docx',
            'settings' => [
                'variables' => ['legacy_only_placeholder'],
            ],
        ]);

        $fromFile = $extractor->extractFromFile($docxPath);
        $this->assertContains('dp_podpisant', $fromFile);

        $template->forceFill([
            'file_disk' => null,
            'file_path' => null,
        ]);

        $storedOnly = $extractor->placeholdersForTemplate($template);
        $this->assertSame(['legacy_only_placeholder'], $storedOnly);

        $merged = $extractor->mergePlaceholderLists(['legacy_only_placeholder'], $fromFile);
        $this->assertContains('dp_podpisant', $merged);
        $this->assertContains('legacy_only_placeholder', $merged);
    }
}
