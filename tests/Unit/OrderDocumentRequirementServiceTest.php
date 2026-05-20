<?php

namespace Tests\Unit;

use App\Models\OrderDocument;
use App\Services\OrderDocumentRequirementService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderDocumentRequirementServiceTest extends TestCase
{
    #[Test]
    public function grouped_carrier_slot_matches_document_with_contractor_and_leg_stage(): void
    {
        $service = app(OrderDocumentRequirementService::class);

        $rules = [
            [
                'key' => 'carrier_request:carrier-20',
                'label' => 'Заявка перевозчику · Б',
                'description' => '',
                'party' => 'carrier',
                'accepted_types' => ['request', 'contract_request'],
                'slot_kind' => 'carrier_request',
                'slot_key' => 'carrier-20',
                'contractor_id' => 20,
                'order_leg_stage' => null,
                'counterparty_label' => 'Б',
            ],
        ];

        $document = new OrderDocument([
            'type' => 'contract_request',
            'status' => 'signed',
            'metadata' => [
                'party' => 'carrier',
                'carrier_contractor_id' => 20,
                'order_leg_stage' => 'leg_2',
            ],
        ]);

        $checklist = $service->checklistForDocuments([$document], $rules);

        $this->assertTrue($checklist[0]['completed']);
    }

    #[Test]
    public function checklist_reads_leg_stage_from_legacy_metadata_stage_field(): void
    {
        $service = app(OrderDocumentRequirementService::class);

        $rules = [
            [
                'key' => 'customer_request:customer-leg_1',
                'label' => 'Заявка заказчика · Плечо 1',
                'description' => '',
                'party' => 'customer',
                'accepted_types' => ['contract_request'],
                'slot_kind' => 'customer_request',
                'slot_key' => 'customer-leg_1',
                'contractor_id' => null,
                'order_leg_stage' => 'leg_1',
                'counterparty_label' => null,
            ],
        ];

        $document = new OrderDocument([
            'type' => 'contract_request',
            'status' => 'signed',
            'metadata' => [
                'party' => 'customer',
                'stage' => 'leg_1',
            ],
        ]);

        $checklist = $service->checklistForDocuments([$document], $rules);

        $this->assertTrue($checklist[0]['completed']);
    }
}
