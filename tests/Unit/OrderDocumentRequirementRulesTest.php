<?php

namespace Tests\Unit;

use App\Models\FinancialTerm;
use App\Models\Order;
use App\Models\OrderDocument;
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
    }

    #[Test]
    public function cash_to_cash_deal_requires_only_requests_and_transport_documents(): void
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

        $this->assertNotNull(collect($rules)->firstWhere('key', 'customer_request:customer-all'));
        $this->assertNotNull(collect($rules)->firstWhere('key', 'carrier_request:carrier-15'));
        $this->assertNotNull(collect($rules)->firstWhere('key', 'waybill'));
        $this->assertNull(collect($rules)->firstWhere('key', 'customer_closing:customer-all'));
        $this->assertNull(collect($rules)->firstWhere('key', 'carrier_closing:carrier-15'));
        $this->assertCount(3, $rules);
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
            'type' => 'contract_request',
            'status' => 'signed',
            'metadata' => ['party' => 'customer'],
        ]);

        $checklist = $service->checklistForDocuments([$document], $rules);
        $customerRequest = collect($checklist)->firstWhere('key', 'customer_request:customer-all');

        $this->assertTrue($customerRequest['completed']);
        $this->assertNull(collect($checklist)->firstWhere('key', 'customer_closing:customer-all'));
    }

    #[Test]
    public function transport_documents_share_unified_waybill_slot_label(): void
    {
        $rules = OrderDocumentRequirementSlotBuilder::buildRules([], 'single_request');
        $waybillRule = collect($rules)->firstWhere('key', 'waybill');

        $this->assertNotNull($waybillRule);
        $this->assertSame(OrderDocumentTransportTypes::UNIFIED_LABEL, $waybillRule['label']);
        $this->assertSame(OrderDocumentTransportTypes::VALUES, $waybillRule['accepted_types']);
        $this->assertSame('carrier', $waybillRule['party']);
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
    public function document_type_options_expose_single_transport_entry(): void
    {
        $options = app(OrderDocumentRequirementService::class)->documentTypeOptions();
        $transportOptions = collect($options)->filter(
            fn (array $option): bool => in_array($option['value'], OrderDocumentTransportTypes::VALUES, true),
        );

        $this->assertCount(1, $transportOptions);
        $this->assertSame(OrderDocumentTransportTypes::UNIFIED_LABEL, $transportOptions->first()['label']);
    }

    #[Test]
    public function own_fleet_carrier_only_with_cash_customer_requires_only_request_and_transport(): void
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
        $this->assertNull(collect($rules)->firstWhere('key', 'carrier_request:carrier-99'));
        $this->assertNull(collect($rules)->firstWhere('key', 'contractor_closing:contractor-32-cost-vat'));
        $this->assertCount(2, $rules);
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
        $this->assertCount(3, $rules);
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
