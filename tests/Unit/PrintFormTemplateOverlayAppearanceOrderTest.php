<?php

namespace Tests\Unit;

use App\Models\PrintFormTemplate;
use App\Support\PrintFormImageOverlayPlaceholders;
use App\Support\PrintFormTemplateOverlayAppearanceOrder;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class PrintFormTemplateOverlayAppearanceOrderTest extends TestCase
{
    #[Test]
    public function it_detects_stamp_before_signature_in_docx(): void
    {
        Storage::fake('local');

        $xml = '<w:body>'
            .'<w:p><w:r><w:t>${'.PrintFormImageOverlayPlaceholders::DEFAULT_STAMP.'}</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>${'.PrintFormImageOverlayPlaceholders::DEFAULT_SIGNATURE.'}</w:t></w:r></w:p>'
            .'</w:body>';

        $path = 'print-form-templates/stamp-first.docx';
        $this->storeMinimalDocx($path, $xml);

        $template = new PrintFormTemplate([
            'file_disk' => 'local',
            'file_path' => $path,
            'settings' => [
                'image_overlays' => [
                    'internal_signature' => ['placeholder' => PrintFormImageOverlayPlaceholders::DEFAULT_SIGNATURE],
                    'internal_stamp' => ['placeholder' => PrintFormImageOverlayPlaceholders::DEFAULT_STAMP],
                ],
            ],
        ]);

        $order = PrintFormTemplateOverlayAppearanceOrder::imageOverlayKeysInReadingOrder($template);

        $this->assertSame(['internal_stamp', 'internal_signature'], $order);
    }

    #[Test]
    public function active_overlay_keys_include_only_overlays_with_uploaded_files(): void
    {
        Storage::fake('local');

        $path = 'print-form-templates/stamp-only.docx';
        $this->storeMinimalDocx($path, '<w:body><w:p><w:r><w:t>${stamp}</w:t></w:r></w:p></w:body>');

        $template = new PrintFormTemplate([
            'file_disk' => 'local',
            'file_path' => $path,
            'settings' => [
                'image_overlays' => [
                    'internal_signature' => [
                        'placeholder' => 'signature',
                        'path' => null,
                        'offset_x_mm' => 5,
                        'offset_y_mm' => 1,
                    ],
                    'internal_stamp' => [
                        'placeholder' => 'stamp',
                        'path' => 'overlays/stamp.png',
                        'offset_x_mm' => -3,
                        'offset_y_mm' => 7,
                    ],
                ],
            ],
        ]);

        $keys = PrintFormImageOverlayPlaceholders::activeOverlayKeysInReadingOrder($template);

        $this->assertSame([PrintFormImageOverlayPlaceholders::KEY_STAMP], $keys);
    }

    private function storeMinimalDocx(string $path, string $documentXml): void
    {
        $absolute = Storage::disk('local')->path($path);
        $dir = dirname($absolute);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $zip = new ZipArchive;
        $zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();
    }
}
