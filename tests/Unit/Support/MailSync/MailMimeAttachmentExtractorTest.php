<?php

namespace Tests\Unit\Support\MailSync;

use App\Support\MailSync\MailMimeAttachmentExtractor;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class MailMimeAttachmentExtractorTest extends TestCase
{
    #[Test]
    public function it_reads_filename_from_single_stdclass_parameter(): void
    {
        $extractor = new MailMimeAttachmentExtractor;
        $method = new ReflectionMethod(MailMimeAttachmentExtractor::class, 'parameterValue');
        $method->setAccessible(true);

        $parameter = (object) [
            'attribute' => 'filename',
            'value' => 'invoice.pdf',
        ];

        $this->assertSame(
            'invoice.pdf',
            $method->invoke($extractor, $parameter, 'filename'),
        );
    }
}
