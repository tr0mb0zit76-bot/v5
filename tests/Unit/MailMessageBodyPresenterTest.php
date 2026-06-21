<?php

namespace Tests\Unit;

use App\Models\MailMessage;
use App\Support\MailSync\MailMessageBodyPresenter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailMessageBodyPresenterTest extends TestCase
{
    #[Test]
    public function plain_text_prefers_html_when_body_text_contains_css_garbage(): void
    {
        $message = new MailMessage([
            'body_text' => ".ExternalClass { width: 100%; }\nimg { border: 0 none; }",
            'body_html' => '<p>Встречное предложение на груз: "#UJJ5461"</p>',
        ]);

        $plain = MailMessageBodyPresenter::plainText($message);

        $this->assertSame('Встречное предложение на груз: "#UJJ5461"', $plain);
    }

    #[Test]
    public function preview_uses_clean_plain_text(): void
    {
        $message = new MailMessage([
            'body_text' => '.ExternalClass { width: 100%; }',
            'body_html' => '<p>Короткий текст письма для превью</p>',
        ]);

        $preview = MailMessageBodyPresenter::preview($message, 20);

        $this->assertNotNull($preview);
        $this->assertStringStartsWith('Короткий текст', $preview);
    }
}
