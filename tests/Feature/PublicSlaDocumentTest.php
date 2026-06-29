<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PublicSlaDocumentTest extends TestCase
{
    public function test_customer_offer_pdf_is_served_inline(): void
    {
        $relativePath = 'documents/sla/customer-offer-test.pdf';
        $absolutePath = public_path($relativePath);

        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, '%PDF-1.4 test');

        config([
            'showcase.sla_documents.customer-offer.public_path' => $relativePath,
        ]);

        $this->get(route('public.sla.document', ['document' => 'customer-offer']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        File::delete($absolutePath);
    }

    public function test_unknown_sla_document_returns_not_found(): void
    {
        $this->get(route('public.sla.document', ['document' => 'missing-doc']))
            ->assertNotFound();
    }
}
