<?php

namespace Tests\Feature;

use App\Models\OrderDocument;
use App\Support\PrintFormVerificationCode;
use Tests\TestCase;

class OrderDocumentVerificationPageTest extends TestCase
{
    public function test_public_verification_page_shows_document_hash_for_valid_code(): void
    {
        $document = OrderDocument::factory()->create([
            'number' => 'REQ-1',
            'metadata' => [
                'pdf_certified_sha256' => str_repeat('a', 64),
                'pdf_certified_at' => '2026-06-12T10:00:00+04:00',
            ],
        ]);

        $response = $this->get(route('print-verification.order-documents.show', [
            'orderDocument' => $document,
            'code' => PrintFormVerificationCode::forOrderDocument($document),
        ]));

        $response->assertOk();
        $response->assertSee('Проверка целостности документа');
        $response->assertSee(str_repeat('a', 64));
        $response->assertDontSee('Сравните хеш', false);
        $response->assertDontSee('Сверьте этот хеш', false);
    }

    public function test_public_verification_page_returns_not_found_for_invalid_code(): void
    {
        $document = OrderDocument::factory()->create();

        $this->get(route('print-verification.order-documents.show', [
            'orderDocument' => $document,
            'code' => 'bad-code',
        ]))->assertNotFound();
    }
}
