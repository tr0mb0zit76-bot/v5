<?php

namespace Tests\Unit;

use App\Services\OrderDocumentRequirementService;
use Tests\TestCase;

class OrderDocumentRequirementServiceTest extends TestCase
{
    public function test_uploaded_document_counts_only_when_status_is_sent_or_signed(): void
    {
        $service = app(OrderDocumentRequirementService::class);

        $incomplete = $service->checklistForDocuments([
            ['type' => 'request', 'party' => 'customer', 'status' => 'draft'],
        ]);

        $this->assertFalse(collect($incomplete)->firstWhere('key', 'customer_request')['completed']);

        $complete = $service->checklistForDocuments([
            ['type' => 'request', 'party' => 'customer', 'status' => 'sent'],
        ]);

        $this->assertTrue(collect($complete)->firstWhere('key', 'customer_request')['completed']);
    }
}
