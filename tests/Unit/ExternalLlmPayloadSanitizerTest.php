<?php

namespace Tests\Unit;

use App\Services\Inference\ExternalLlmPayloadSanitizer;
use Tests\TestCase;

class ExternalLlmPayloadSanitizerTest extends TestCase
{
    private ExternalLlmPayloadSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.sanitizer.enabled' => true]);
        $this->sanitizer = new ExternalLlmPayloadSanitizer;
    }

    public function test_redacts_email_and_phone_in_text(): void
    {
        $input = 'Свяжитесь: ivan@test.ru или +7 (916) 123-45-67';

        $result = $this->sanitizer->sanitizeText($input, 'command_bar');

        $this->assertStringNotContainsString('ivan@test.ru', $result);
        $this->assertStringNotContainsString('916', $result);
        $this->assertStringContainsString('[redacted]', $result);
    }

    public function test_redacts_sensitive_fields_in_structured_payload(): void
    {
        $payload = [
            'order_number' => 'ORD-100',
            'customer_contact_phone' => '+79991234567',
            'customer_contact_name' => 'Иван Иванов',
        ];

        $result = $this->sanitizer->sanitizeStructured($payload, 'command_bar');

        $this->assertSame('ORD-100', $result['order_number']);
        $this->assertSame('[redacted]', $result['customer_contact_phone']);
        $this->assertSame('[redacted]', $result['customer_contact_name']);
    }

    public function test_trainer_profile_redacts_numeric_entity_ids(): void
    {
        $payload = [
            'order_id' => 42,
            'task_id' => '15',
            'order_number' => 'ORD-42',
        ];

        $result = $this->sanitizer->sanitizeStructured($payload, 'trainer');

        $this->assertSame('[redacted_id]', $result['order_id']);
        $this->assertSame('[redacted_id]', $result['task_id']);
        $this->assertSame('ORD-42', $result['order_number']);
    }

    public function test_command_bar_profile_keeps_entity_ids(): void
    {
        $payload = [
            'order_id' => 42,
            'email' => 'secret@corp.ru',
        ];

        $result = $this->sanitizer->sanitizeStructured($payload, 'command_bar');

        $this->assertSame(42, $result['order_id']);
        $this->assertSame('[redacted]', $result['email']);
    }

    public function test_sanitize_tool_message_json_content(): void
    {
        $messages = [
            [
                'role' => 'tool',
                'tool_call_id' => 'call_1',
                'content' => json_encode([
                    'order_id' => 7,
                    'customer_contact_email' => 'a@b.c',
                ], JSON_UNESCAPED_UNICODE),
            ],
        ];

        $result = $this->sanitizer->sanitizeMessages($messages, 'command_bar');

        $decoded = json_decode((string) $result[0]['content'], true);

        $this->assertSame(7, $decoded['order_id']);
        $this->assertSame('[redacted]', $decoded['customer_contact_email']);
    }

    public function test_disabled_sanitizer_returns_original_text(): void
    {
        config(['ai.sanitizer.enabled' => false]);

        $input = 'mail@test.ru';

        $this->assertSame($input, $this->sanitizer->sanitizeText($input));
    }
}
