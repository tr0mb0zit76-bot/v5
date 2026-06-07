<?php

namespace Tests\Unit\Support\MailSync;

use App\Support\MailSync\MailMimeBodyExtractor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailMimeAttachmentDecodeTest extends TestCase
{
    #[Test]
    public function decode_binary_content_preserves_pdf_bytes(): void
    {
        $pdf = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF";
        $base64 = base64_encode($pdf);

        $decoded = MailMimeBodyExtractor::decodeBinaryContent($base64, 3);

        $this->assertSame($pdf, $decoded);
        $this->assertStringStartsWith('%PDF-', $decoded);
    }

    #[Test]
    public function decode_content_does_not_preserve_invalid_utf8_binary(): void
    {
        $binary = "\x25PDF-1.4\xFF\xFE binary chunk";
        $base64 = base64_encode($binary);

        $decodedAsText = MailMimeBodyExtractor::decodeContent($base64, 3, null);
        $decodedAsBinary = MailMimeBodyExtractor::decodeBinaryContent($base64, 3);

        $this->assertSame($binary, $decodedAsBinary);
        $this->assertNotSame($binary, $decodedAsText);
    }
}
