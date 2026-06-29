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

    public function test_one_c_fresh_can_read_etrn_documents_with_token(): void
    {
        config()->set('epd.integration.one_c_fresh_token', 'one-c-token');

        $orderId = $this->insertOrderRow(['created_at' => now(), 'updated_at' => now()]);
        DB::table('order_documents')->insert([
            [
                'order_id' => $orderId,
                'type' => 'etrn',
                'status' => 'sent',
                'number' => 'ETRN-77',
                'metadata' => json_encode(['epd' => ['external_id' => 'ext-77', 'gis_status' => 'sent']], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $orderId,
                'type' => 'waybill',
                'status' => 'sent',
                'number' => 'WB-1',
                'metadata' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this
            ->withHeader('X-Integration-Token', 'one-c-token')
            ->get('/integrations/1c-fresh/orders/'.$orderId.'/etrn-documents');

        $response->assertOk();
        $response->assertJsonCount(1, 'documents');
        $response->assertJsonPath('documents.0.number', 'ETRN-77');
        $response->assertJsonPath('documents.0.external_id', 'ext-77');
    }

    public function test_one_c_fresh_can_create_etrn_draft_document_from_order(): void
    {
        config()->set('epd.integration.one_c_fresh_token', 'one-c-token');

        $orderId = $this->insertOrderRow([
            'order_number' => 'ORD-100',
            'loading_date' => '2026-05-06',
            'unloading_date' => '2026-05-07',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->withHeader('X-Integration-Token', 'one-c-token')
            ->postJson('/integrations/1c-fresh/etrn/create-from-order', [
                'order_id' => $orderId,
                'allow_incomplete' => true,
            ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('status', 'draft');
        $this->assertDatabaseHas('order_documents', [
            'order_id' => $orderId,
            'type' => 'etrn',
        ]);
    }

    public function test_one_c_fresh_create_etrn_requires_required_fields_unless_override_enabled(): void
    {
        config()->set('epd.integration.one_c_fresh_token', 'one-c-token');

        $orderId = $this->insertOrderRow([
            'order_number' => null,
            'loading_date' => null,
            'unloading_date' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->withHeader('X-Integration-Token', 'one-c-token')
            ->postJson('/integrations/1c-fresh/etrn/create-from-order', [
                'order_id' => $orderId,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.order.0', 'Недостаточно данных для формирования ЭТрН-пакета.');
    }

    public function test_one_c_fresh_can_get_latest_etrn_draft_by_order(): void
    {
        config()->set('epd.integration.one_c_fresh_token', 'one-c-token');

        $orderId = $this->insertOrderRow(['created_at' => now(), 'updated_at' => now()]);
        DB::table('order_documents')->insert([
            [
                'order_id' => $orderId,
                'type' => 'etrn',
                'status' => 'draft',
                'number' => 'ETRN-OLD',
                'metadata' => json_encode(['etrn_missing_required_fields' => ['order.order_number']], JSON_THROW_ON_ERROR),
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
            [
                'order_id' => $orderId,
                'type' => 'etrn',
                'status' => 'pending',
                'number' => 'ETRN-NEW',
                'metadata' => json_encode(['etrn_missing_required_fields' => []], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this
            ->withHeader('X-Integration-Token', 'one-c-token')
            ->get('/integrations/1c-fresh/orders/'.$orderId.'/etrn-latest-draft');

        $response->assertOk();
        $response->assertJsonPath('document.number', 'ETRN-NEW');
        $response->assertJsonPath('document.order_id', $orderId);
    }

    public function test_one_c_fresh_can_get_etrn_journal_with_order_linkage(): void
    {
        config()->set('epd.integration.one_c_fresh_token', 'one-c-token');

        $orderIdOne = $this->insertOrderRow(['created_at' => now(), 'updated_at' => now()]);
        $orderIdTwo = $this->insertOrderRow(['created_at' => now(), 'updated_at' => now()]);

        DB::table('order_documents')->insert([
            [
                'order_id' => $orderIdOne,
                'type' => 'etrn',
                'status' => 'draft',
                'number' => 'ETRN-1',
                'metadata' => json_encode(['etrn_missing_required_fields' => ['order.loading_date']], JSON_THROW_ON_ERROR),
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
            [
                'order_id' => $orderIdTwo,
                'type' => 'etrn',
                'status' => 'sent',
                'number' => 'ETRN-2',
                'metadata' => json_encode(['epd' => ['external_id' => 'ext-2', 'gis_status' => 'sent']], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $orderIdTwo,
                'type' => 'waybill',
                'status' => 'sent',
                'number' => 'WB-2',
                'metadata' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this
            ->withHeader('X-Integration-Token', 'one-c-token')
            ->get('/integrations/1c-fresh/etrn-journal');

        $response->assertOk();
        $response->assertJsonCount(2, 'journal');
        $response->assertJsonPath('journal.0.number', 'ETRN-2');
        $response->assertJsonPath('journal.0.order_id', $orderIdTwo);
        $response->assertJsonPath('journal.1.order_id', $orderIdOne);
    }
}
