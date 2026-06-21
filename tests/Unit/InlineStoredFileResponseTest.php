<?php

namespace Tests\Unit;

use App\Support\InlineStoredFileResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InlineStoredFileResponseTest extends TestCase
{
    #[Test]
    public function pdf_and_images_use_inline_disposition(): void
    {
        $this->assertStringStartsWith(
            'inline',
            InlineStoredFileResponse::disposition('application/pdf', 'scan.pdf'),
        );
        $this->assertStringStartsWith(
            'inline',
            InlineStoredFileResponse::disposition('image/jpeg', 'photo.jpg'),
        );
    }

    #[Test]
    public function other_mime_types_use_attachment_disposition(): void
    {
        $this->assertStringStartsWith(
            'attachment',
            InlineStoredFileResponse::disposition('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'doc.docx'),
        );
    }
}
