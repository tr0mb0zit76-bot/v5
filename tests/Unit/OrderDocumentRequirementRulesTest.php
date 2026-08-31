<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Models\FinancialTerm;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\OrderDocumentEdoAcknowledgement;
use App\Services\OrderDocumentRequirementService;
use App\Support\OrderDocumentRequirementSlotBuilder;
use App\Support\OrderDocumentTransportTypes;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderDocumentRequirementRulesTest extends TestCase
{
    #[Test]
    public function closing_rules_do_not_accept_transport_documents(): void
    {
        $rules = OrderDocumentRequirementSlotBuilder::buildRules([], 'single_request');

        $customerClosing = collect($rules)->firstWhere('key', 'customer_closing:customer-all');

        $this->assertNotNull($customerClosing);
        $this->assertSame(['upd', 'invoice_factura', 'act'], $customerClosing['accepted_types']);
    }

    #[Test]
    public function cash_customer_payment_omits_closing_requirement(): void
    {
        $rules = OrderDocumentRequirementSlotBuilder::buildRules([], 'single_request', [], [
            'customer' => 'cash',
            'carriers' => [],
        ]);

        $this->assertNull(collect($rules)->firstWhere('key', 'customer_closing:customer-all'));
        $this->assertNotNull(collect($rules)->firstWhere('key', 'customer_request:customer-all'));
        $this->assertNotNull(collect($rules)->firstWhere('key', 'waybill'));
    }

    #[Test]
    public function cash_to_cash_deal_omits_customer_request_and_transport(): void
    {
        $performers = [[
            'stage' => 'leg_1',
            'contractor_id' => 15,
            'contractor_name' => 'Перевозчик',
        ]];

        $rules = OrderDocumentRequirementSlotBuilder::buildRules($performers, 'single_request', [], [
            'customer' => 'cash',
            'carriers' => [15 => 'cash'],
        ]);

        $this->assertNull(collect($rules)->firstWhere('key', 'customer_request:customer-all'));
        $this->assertNull(collect($rules)->firstWhere('key', 'carrier_request:carrier-15'));
        $this->assertNull(collect($rules)->firstWhere('key', 'waybill'));
        $this->assertNull(collect($rules)->firstWhere('key', 'etrn'));
        $this->assertNull(collect($rules)->firstWhere('key', 'customer_closing:customer-all'));
        $this->assertNull(collect($rules)->firstWhere('key', 'carrier_closing:carrier-15'));
        $this->assertCount(0, $rules);
    }

    #[Test]
    public function cash_carrier_payment_omits_carrier_request_and_closing(): void
    {
        $performers = [[
            'stage' => 'leg_1',
            'contractor_id' => 15,
            'contractor_name' => 'Перевозчик',
        ]];

        $rules = OrderDocumentRequirementSlotBuilder::buildRules($performers, 'single_request', [], [
            'customer' => 'vat_20',
            'carriers' => [15 => 'cash'],
        ]);

        $this->assertNull(collect($rules)->firstWhere('key', 'carrier_request:carrier-15'));
        $this->assertNull(collect($rules)->firstWhere('key', 'carrier_closing:carrier-15'));
        $this->assertNotNull(collect($rules)->firstWhere('key', 'customer_request:customer-all'));
        $this->assertNotNull(collect($rules)->firstWhere('key', 'customer_closing:customer-all'));
        $this->assertNotNull(collect($rules)->firstWhere('key', 'waybill'));
    }

    #[Test]
    public function mixed_cash_and_non_cash_counterparties_require_closing_only_for_non_cash(): void
    {
        $performers = [[
            'stage' => 'leg_1',
            'contractor_id' => 20,
            'contractor_name' => 'Перевозчик безнал',
        ]];

        $additionalCosts = [
            [
                'id' => 'cost-cash',
                'contractor_id' => 31,
                'contractor_name' => 'Подрядчик нал',
                'payment_form' => 'cash',
            ],
            [
                'id' => 'cost-vat',
                'contractor_id' => 32,
                'contractor_name' => 'Подрядчик безнал',
                'payment_form' => 'vat_20',
            ],
        ];

        $rules = OrderDocumentRequirementSlotBuilder::buildRules($performers, 'single_request', $additionalCosts, [
            'customer' => 'vat_20',
            'carriers' => [20 => 'vat_20'],
        ]);

        $this->assertNotNull(collect($rules)->firstWhere('key', 'customer_request:customer-all'));
        $this->assertNotNull(collect($rules)->firstWhere('key', 'carrier_request:carrier-20'));
        $this->assertNotNull(collect($rules)->firstWhere('key', 'customer_closing:customer-all'));
        $this->assertNotNull(collect($rules)->firstWhere('key', 'carrier_closing:carrier-20'));
        $this->assertNull(collect($rules)->firstWhere('key', 'contractor_closing:contractor-31-cost-cash'));
        $this->assertNotNull(collect($rules)->firstWhere('key', 'contractor_closing:contractor-32-cost-vat'));
        $this->assertNotNull(collect($rules)->firstWhere('key', 'waybill'));

        $closingRules = collect($rules)->filter(
            fn (array $rule): bool => str_contains((string) ($rule['slot_kind'] ?? ''), 'closing'),
        );

        $this->assertCount(3, $closingRules);
    }

    #[Test]
    public function cash_closing_is_not_required_even_when_only_act_is_uploaded(): void
    {
        $service = app(OrderDocumentRequirementService::class);

        $rules = OrderDocumentRequirementSlotBuilder::buildRules([], 'single_request', [], [
            'customer' => 'cash',
            'carriers' => [],
        ]);

        $document = new OrderDocument([
            'type' => 'act',
            'status' => 'signed',
            'metadata' => ['party' => 'customer'],
        ]);

        $checklist = $service->checklistForDocuments([$document], $rules);

        $this->assertNotNull(collect($checklist)->firstWhere('key', 'customer_request:customer-all'));
        $this->assertFalse(collect($checklist)->firstWhere('key', 'customer_request:customer-all')['completed']);
        $this->assertNull(collect($checklist)->firstWhere('key', 'customer_closing:customer-all'));
        $this->assertNotNull(collect($checklist)->firstWhere('key', 'waybill'));
        $this->assertFalse(collect($checklist)->firstWhere('key', 'waybill')['completed']);
    }

    #[Test]
    public function non_cash_customer_request_completes_with_signed_contract_request(): void
    {
        $service = app(OrderDocumentRequirementService::class);

        $rules = OrderDocumentRequirementSlotBuilder::buildRules([], 'single_request', [], [
            'customer' => 'vat_20',
            'carriers' => [],
        ]);

        $document = new OrderDocument([
            'type' => 'contract_request',
            'status' => 'signed',
            'metadata' => ['party' => 'customer'],
        ]);

        $checklist = $service->checklistForDocuments([$document], $rules);
        $customerRequest = collect($checklist)->firstWhere('key', 'customer_request:customer-all');

        $this->assertNotNull($customerRequest);
        $this->assertTrue($customerRequest['completed']);
        $this->assertNotNull(collect($checklist)->firstWhere('key', 'customer_closing:customer-all'));
    }

    #[Test]
    public function non_cash_customer_request_completes_with_edo_acknowledgement(): void
    {
        $service = app(OrderDocumentRequirementService::class);

        $rules = OrderDocumentRequirementSlotBuilder::buildRules([], 'single_request', [], [
            'customer' => 'vat_20',
            'carriers' => [],
        ]);

        $acknowledgement = new OrderDocumentEdoAcknowledgement([
            'party' => 'customer',
            'document_type' => 'request',
            'slot_key' => 'customer-all',
            'contractor_id' => 0,
            'received_via_edo' => true,
            'document_number' => 'ZAY-EDO-1',
        ]);

        $checklist = $service->checklistForDocuments([], $rules, [$acknowledgement]);
        $customerRequest = collect($checklist)->firstWhere('key', 'customer_request:customer-all');

        $this->assertNotNull($customerRequest);
        $this->assertTrue($customerRequest['completed']);
    }

    #[Test]
    public function paper_waybill_fulfills_etrn_checklist_slot_as_alternative(): void
    {
        $rules = OrderDocumentRequirementSlotBuilder::buildRules([], 'single_request');
        $service = app(OrderDocumentRequirementService::class);

        $document = new OrderDocument([
            'id' => 969,
            'type' => 'waybill',
            'status' => 'signed',
            'metadata' => ['party' => 'carrier', 'requirement_slot_key' => 'waybill'],
        ]);

        $checklist = collect($service->checklistForDocuments([$document], $rules));
        $waybill = $checklist->firstWhere('key', 'waybill');
        $etrn = $checklist->firstWhere('key', 'etrn');

        $this->assertTrue($waybill['completed']);
        $this->assertTrue($etrn['completed']);
        $this->assertSame('waybill', $etrn['fulfilled_by_alternative'] ?? null);
    }

    #[Test]
    public function etrn_file_fulfills_waybill_checklist_slot_as_alternative(): void
    {
        $rules = OrderDocumentRequirementSlotBuilder::buildRules([], 'single_request');
        $service = app(OrderDocumentRequirementService::class);

        $document = new OrderDocument([
            'id' => 1001,
            'type' => 'etrn',
            'status' => 'signed',
            'metadata' => ['party' => 'carrier'],
        ]);

        $checklist = collect($service->checklistForDocuments([$document], $rules));
        $waybill = $checklist->firstWhere('key', 'waybill');
        $etrn = $checklist->firstWhere('key', 'etrn');

        $this->assertTrue($etrn['completed']);
        $this->assertTrue($waybill['completed']);
        $this->assertSame('etrn', $waybill['fulfilled_by_alternative'] ?? null);
    }

    #[Test]
    public function transport_documents_share_unified_waybill_slot_label(): void
    {
        $rules = OrderDocumentRequirementSlotBuilder::buildRules([], 'single_request');
        $waybillRule = collect($rules)->firstWhere('key', 'waybill');

        $this->assertNotNull($waybillRule);
        $this->assertSame(OrderDocumentTransportTypes::UNIFIED_LABEL, $waybillRule['label']);
        $this->assertSame(OrderDocumentTransportTypes::PAPER_VALUES, $waybillRule['accepted_types']);
        $this->assertSame('carrier', $waybillRule['party']);

        $etrnRule = collect($rules)->firstWhere('key', 'etrn');
        $this->assertNotNull($etrnRule);
        $this->assertTrue($etrnRule['is_required']);
        $this->assertSame(['etrn'], $etrnRule['accepted_types']);

        $this->assertNull(collect($rules)->firstWhere('key', 'expedition_receipt'));
    }

    #[Test]
    public function waybill_slot_matches_legacy_internal_and_carrier_transport_documents(): void
    {
        $performers = [[
            'stage' => 'leg_1',
            'contractor_id' => 5,
            'contractor_name' => 'ООО Перевоз',
        ]];

        $rules = OrderDocumentRequirementSlotBuilder::buildRules($performers, 'single_request');
        $waybillRule = collect($rules)->firstWhere('key', 'waybill');

        $this->assertSame('carrier', $waybillRule['party']);
        $this->assertSame('ООО Перевоз', $waybillRule['counterparty_label']);

        $service = app(OrderDocumentRequirementService::class);

        foreach (['internal', 'carrier'] as $party) {
            $document = new OrderDocument([
                'type' => 'cmr',
                'status' => 'signed',
                'metadata' => ['party' => $party],
            ]);

            $checklist = $service->checklistForDocuments([$document], $rules);
            $waybill = collect($checklist)->firstWhere('key', 'waybill');

            $this->assertNotNull($waybill, "waybill checklist missing for party {$party}");
            $this->assertTrue($waybill['completed'], "waybill not completed for party {$party}");
        }
    }

    #[Test]
    public function requirement_rules_for_order_omit_customer_closing_when_cash(): void
    {
        $order = Order::factory()->create([
            'customer_payment_form' => 'cash',
        ]);

        FinancialTerm::factory()->create([
            'order_id' => $order->id,
            'contractors_costs' => [],
        ]);

        $service = app(OrderDocumentRequirementService::class);
        $rules = $service->requirementRulesForOrder($order);

        $this->assertNull(collect($rules)->firstWhere('key', 'customer_closing:customer-all'));
    }

    #[Test]
    public function requirement_rules_for_order_flag_expects_edo_from_customer_edo_fields(): void
    {
        $customer = Contractor::query()->create([
            'name' => 'ООО С ЭДО',
            'type' => 'customer',
            'edo_provider' => 'diadoc',
            'edo_number' => '2BE-TEST',
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'customer_payment_form' => 'bank_transfer',
        ]);

        FinancialTerm::factory()->create([
            'order_id' => $order->id,
            'contractors_costs' => [],
        ]);

        $rules = app(OrderDocumentRequirementService::class)->requirementRulesForOrder($order);
        $customerRequest = collect($rules)->firstWhere('key', 'customer_request:customer-all');
        $customerClosing = collect($rules)->firstWhere('key', 'customer_closing:customer-all');

        $this->assertNotNull($customerRequest);
        $this->assertTrue($customerRequest['expects_edo']);
        $this->assertNotNull($customerClosing);
        $this->assertTrue($customerClosing['expects_edo']);
    }

    #[Test]
    public function document_type_options_expose_paper_and_epd_entries(): void
    {
        $options = app(OrderDocumentRequirementService::class)->documentTypeOptions();
        $byValue = collect($options)->keyBy('value');

        $this->assertSame(OrderDocumentTransportTypes::UNIFIED_LABEL, $byValue->get('waybill')['label']);
        $this->assertSame(OrderDocumentTransportTypes::ETRN_LABEL, $byValue->get('etrn')['label']);
        $this->assertSame(
            OrderDocumentTransportTypes::EXPEDITION_RECEIPT_LABEL,
            $byValue->get('expedition_receipt')['label'],
        );
    }

    #[Test]
    public function own_fleet_carrier_only_cash_to_cash_has_empty_checklist(): void
    {
        $performers = [[
            'stage' => 'leg_1',
            'contractor_id' => 99,
            'contractor_name' => 'Собственный парк',
            'execution_mode' => 'own_fleet',
        ]];

        $rules = OrderDocumentRequirementSlotBuilder::buildRules($performers, 'single_request', [], [
            'customer' => 'cash',
            'carriers' => [99 => 'cash'],
        ]);

        $this->assertSame([], $rules);
    }

    #[Test]
    public function own_fleet_carrier_only_with_cash_customer_and_non_cash_additional_keeps_request_and_transport(): void
    {
        $performers = [[
            'stage' => 'leg_1',
            'contractor_id' => 99,
            'contractor_name' => 'Собственный парк',
            'execution_mode' => 'own_fleet',
        ]];

        $additionalCosts = [[
            'id' => 'cost-vat',
            'contractor_id' => 32,
            'contractor_name' => 'Подрядчик',
            'payment_form' => 'vat_20',
        ]];

        $rules = OrderDocumentRequirementSlotBuilder::buildRules($performers, 'single_request', $additionalCosts, [
            'customer' => 'cash',
            'carriers' => [99 => 'cash'],
        ]);

        $this->assertNotNull(collect($rules)->firstWhere('key', 'customer_request:customer-all'));
        $this->assertNull(collect($rules)->firstWhere('key', 'customer_closing:customer-all'));
        $this->assertNotNull(collect($rules)->firstWhere('key', 'waybill'));
        $this->assertNotNull(collect($rules)->firstWhere('key', 'etrn'));
        $this->assertNull(collect($rules)->firstWhere('key', 'carrier_request:carrier-99'));
        $this->assertNull(collect($rules)->firstWhere('key', 'contractor_closing:contractor-32-cost-vat'));
        $this->assertCount(3, $rules);
    }

    #[Test]
    public function own_fleet_carrier_only_with_non_cash_customer_requires_closing(): void
    {
        $performers = [[
            'stage' => 'leg_1',
            'contractor_id' => 99,
            'contractor_name' => 'Собственный парк',
            'execution_mode' => 'own_fleet',
        ]];

        $rules = OrderDocumentRequirementSlotBuilder::buildRules($performers, 'single_request', [], [
            'customer' => 'vat_20',
            'carriers' => [99 => 'cash'],
        ]);

        $this->assertNotNull(collect($rules)->firstWhere('key', 'customer_request:customer-all'));
        $this->assertNotNull(collect($rules)->firstWhere('key', 'customer_closing:customer-all'));
        $this->assertNotNull(collect($rules)->firstWhere('key', 'waybill'));
        $this->assertNotNull(collect($rules)->firstWhere('key', 'etrn'));
        $this->assertNull(collect($rules)->firstWhere('key', 'expedition_receipt'));
        $this->assertCount(4, $rules);
    }

    #[Test]
    public function mixed_own_fleet_and_external_carrier_keeps_external_carrier_rules(): void
    {
        $performers = [[
            'stage' => 'leg_1',
            'carrier_mode' => 'split',
            'split_carriers' => [
                [
                    'slot' => 1,
                    'contractor_id' => 10,
                    'contractor_name' => 'Внешний',
                    'execution_mode' => null,
                ],
                [
                    'slot' => 2,
                    'contractor_id' => 99,
                    'contractor_name' => 'Собственный парк',
                    'execution_mode' => 'own_fleet',
                ],
            ],
        ]];

        $rules = OrderDocumentRequirementSlotBuilder::buildRules($performers, 'single_request', [], [
            'customer' => 'vat_20',
            'carriers' => [10 => 'vat_20', 99 => 'cash'],
        ]);

        $this->assertNotNull(collect($rules)->first(fn (array $rule): bool => str_starts_with((string) ($rule['key'] ?? ''), 'carrier_request:')));
        $this->assertNotNull(collect($rules)->first(fn (array $rule): bool => str_starts_with((string) ($rule['key'] ?? ''), 'carrier_closing:')));
    }
}
