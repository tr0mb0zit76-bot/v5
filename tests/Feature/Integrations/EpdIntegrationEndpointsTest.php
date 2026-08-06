<?php

namespace Tests\Feature\Integrations;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EpdIntegrationEndpointsTest extends TestCase
{
    public function test_astral_webhook_updates_etrn_status_when_signature_is_valid(): void
    {
        config()->set('epd.operator.webhook_secret', 'test-secret');

        $orderId = $this->insertOrderRow(['created_at' => now(), 'updated_at' => now()]);
        $documentId = DB::table('order_documents')->insertGetId([
            'order_id' => $orderId,
            'type' => 'etrn',
            'status' => 'draft',
            'metadata' => json_encode(['epd' => ['external_id' => 'ext-1']], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'event_id' => 'evt-1',
            'event_type' => 'document.status_changed',
            'document' => [
                'crm_document_id' => $documentId,
                'external_id' => 'ext-1',
                'status' => 'signed',
            ],
        ];
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $raw, 'test-secret');

        $response = $this->call(
            'POST',
            '/integrations/astral/epd/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_EPD_SIGNATURE' => $signature,
            ],
            $raw,
        );

        $response->assertOk()->assertJson([
            'ok' => true,
            'matched' => true,
            'document_id' => $documentId,
        ]);

        $this->assertDatabaseHas('order_documents', [
            'id' => $documentId,
            'status' => 'signed',
        ]);
    }

    public function test_astral_webhook_rejects_invalid_signature(): void
    {
        config()->set('epd.operator.webhook_secret', 'test-secret');

        $response = $this
            ->withHeader('X-Epd-Signature', 'bad-signature')
            ->postJson('/integrations/astral/epd/webhook', [
                'event_id' => 'evt-2',
                'event_type' => 'document.status_changed',
                'document' => ['status' => 'sent'],
            ]);

        $response->assertStatus(401);
    }
}
