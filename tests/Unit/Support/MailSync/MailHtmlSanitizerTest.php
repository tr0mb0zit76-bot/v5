<?php

namespace Tests\Unit\Support\MailSync;

use App\Support\MailSync\MailHtmlSanitizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailHtmlSanitizerTest extends TestCase
{
    #[Test]
    public function it_strips_scripts_and_event_handlers(): void
    {
        $html = '<p onclick="alert(1)">Hi</p><script>alert(1)</script><style>.x{}</style>';

        $sanitized = MailHtmlSanitizer::sanitize($html);

        $this->assertNotNull($sanitized);
        $this->assertStringContainsString('<p', $sanitized);
        $this->assertStringNotContainsString('<script', $sanitized);
        $this->assertStringNotContainsString('onclick', $sanitized);
        $this->assertStringNotContainsString('<style', $sanitized);
    }
}
