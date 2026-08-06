<?php

namespace Tests\Unit;

use App\Support\DocumentRegistryGridColumnApplicabilityResolver;
use App\Support\OrderDocumentRequirementSlotBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentRegistryGridColumnApplicabilityResolverTest extends TestCase
{
    #[Test]
    public function cash_deal_marks_customer_and_carrier_closing_columns_as_not_applicable(): void
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

        $map = DocumentRegistryGridColumnApplicabilityResolver::mapFromRules($rules);

        $this->assertFalse($map['customer_upd']);
        $this->assertFalse($map['customer_act']);
        $this->assertFalse($map['carrier_upd']);
        $this->assertTrue($map['customer_request']);
        $this->assertTrue($map['carrier_request']);
    }

    #[Test]
    public function own_fleet_with_cash_customer_marks_all_carrier_columns_as_not_applicable(): void
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

        $map = DocumentRegistryGridColumnApplicabilityResolver::mapFromRules($rules);

        $this->assertFalse($map['customer_upd']);
        $this->assertFalse($map['carrier_upd']);
        $this->assertFalse($map['carrier_request']);
        $this->assertFalse($map['carrier_contract_request']);
        $this->assertTrue($map['customer_request']);
    }

    #[Test]
    public function non_cash_deal_keeps_closing_columns_applicable(): void
    {
        $performers = [[
            'stage' => 'leg_1',
            'contractor_id' => 20,
            'contractor_name' => 'Перевозчик',
        ]];

        $rules = OrderDocumentRequirementSlotBuilder::buildRules($performers, 'single_request', [], [
            'customer' => 'vat_20',
            'carriers' => [20 => 'vat_20'],
        ]);

        $map = DocumentRegistryGridColumnApplicabilityResolver::mapFromRules($rules);

        $this->assertTrue($map['customer_upd']);
        $this->assertTrue($map['carrier_upd']);
    }
}
