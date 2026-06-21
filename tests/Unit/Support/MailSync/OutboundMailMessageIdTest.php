<?php

namespace Tests\Unit\Support\MailSync;

use App\Support\MailSync\OutboundMailMessageId;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OutboundMailMessageIdTest extends TestCase
{
    #[Test]
    public function it_generates_message_id_with_sender_domain(): void
    {
        $messageId = OutboundMailMessageId::generate('Manager@Example.COM');

        $this->assertMatchesRegularExpression('/^<[0-9a-f-]{36}@example\.com>$/i', $messageId);
    }

    #[Test]
    public function it_falls_back_to_app_host_when_email_is_invalid(): void
    {
        config(['app.url' => 'https://crm.test']);

        $messageId = OutboundMailMessageId::generate('not-an-email');

        $this->assertStringEndsWith('@crm.test>', $messageId);
    }
}
