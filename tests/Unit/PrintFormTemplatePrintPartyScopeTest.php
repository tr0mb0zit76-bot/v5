<?php

namespace Tests\Unit;

use App\Services\PrintFormTemplateOrderEligibility;
use App\Support\PrintFormTemplateTransportScope;
use Tests\TestCase;

class PrintFormTemplatePrintPartyScopeTest extends TestCase
{
    public function test_effective_print_party_derives_customer_and_carrier_from_internal_templates(): void
    {
        $eligibility = app(PrintFormTemplateOrderEligibility::class);

        $this->assertSame(
            'customer',
            $eligibility->effectivePrintParty([
                'party' => 'internal',
                'has_customer_basic_terms' => true,
                'has_carrier_basic_terms' => false,
            ]),
        );

        $this->assertSame(
            'carrier',
            $eligibility->effectivePrintParty([
                'party' => 'internal',
                'has_customer_basic_terms' => false,
                'has_carrier_basic_terms' => true,
            ]),
        );

        $this->assertSame(
            'dual',
            $eligibility->effectivePrintParty([
                'party' => 'internal',
                'has_customer_basic_terms' => true,
                'has_carrier_basic_terms' => true,
            ]),
        );
    }

    public function test_matches_print_slot_party_blocks_cross_side_templates(): void
    {
        $eligibility = app(PrintFormTemplateOrderEligibility::class);

        $customerTemplate = [
            'party' => 'internal',
            'has_customer_basic_terms' => true,
            'has_carrier_basic_terms' => false,
        ];

        $carrierTemplate = [
            'party' => 'internal',
            'has_customer_basic_terms' => false,
            'has_carrier_basic_terms' => true,
        ];

        $this->assertTrue($eligibility->matchesPrintSlotParty($customerTemplate, 'customer'));
        $this->assertFalse($eligibility->matchesPrintSlotParty($customerTemplate, 'carrier'));
        $this->assertTrue($eligibility->matchesPrintSlotParty($carrierTemplate, 'carrier'));
        $this->assertFalse($eligibility->matchesPrintSlotParty($carrierTemplate, 'customer'));
        $this->assertFalse($eligibility->matchesPrintSlotParty([
            'party' => 'internal',
            'has_customer_basic_terms' => true,
            'has_carrier_basic_terms' => true,
        ], 'customer'));
    }

    public function test_resolve_default_template_returns_separate_defaults_for_customer_and_carrier(): void
    {
        $eligibility = app(PrintFormTemplateOrderEligibility::class);

        $templates = collect([
            [
                'id' => 1,
                'entity_type' => 'order',
                'party' => 'internal',
                'own_company_id' => 101,
                'transport_scope' => PrintFormTemplateTransportScope::DOMESTIC,
                'is_default' => true,
                'is_active' => true,
                'file_path' => 'customer.docx',
                'has_customer_basic_terms' => true,
                'has_carrier_basic_terms' => false,
            ],
            [
                'id' => 2,
                'entity_type' => 'order',
                'party' => 'internal',
                'own_company_id' => 101,
                'transport_scope' => PrintFormTemplateTransportScope::DOMESTIC,
                'is_default' => true,
                'is_active' => true,
                'file_path' => 'carrier.docx',
                'has_customer_basic_terms' => false,
                'has_carrier_basic_terms' => true,
            ],
        ]);

        $customerDefault = $eligibility->resolveDefaultTemplate($templates, 101, false, 'customer');
        $carrierDefault = $eligibility->resolveDefaultTemplate($templates, 101, false, 'carrier');

        $this->assertIsArray($customerDefault);
        $this->assertIsArray($carrierDefault);
        $this->assertSame(1, $customerDefault['id']);
        $this->assertSame(2, $carrierDefault['id']);
    }
}
