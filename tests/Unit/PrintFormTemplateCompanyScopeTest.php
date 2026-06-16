<?php

namespace Tests\Unit;

use App\Services\PrintFormTemplateOrderEligibility;
use App\Support\PrintFormTemplateTransportScope;
use Tests\TestCase;

class PrintFormTemplateCompanyScopeTest extends TestCase
{
    public function test_prefer_own_company_specific_templates_hides_universal_when_company_templates_exist(): void
    {
        $companyAId = 101;
        $companyBId = 202;

        $templates = collect([
            [
                'id' => 1,
                'entity_type' => 'order',
                'party' => 'customer',
                'own_company_id' => null,
                'transport_scope' => PrintFormTemplateTransportScope::DOMESTIC,
                'is_default' => false,
                'is_active' => true,
                'file_path' => 'a.docx',
            ],
            [
                'id' => 2,
                'entity_type' => 'order',
                'party' => 'customer',
                'own_company_id' => $companyAId,
                'transport_scope' => PrintFormTemplateTransportScope::DOMESTIC,
                'is_default' => true,
                'is_active' => true,
                'file_path' => 'b.docx',
            ],
            [
                'id' => 3,
                'entity_type' => 'order',
                'party' => 'customer',
                'own_company_id' => $companyBId,
                'transport_scope' => PrintFormTemplateTransportScope::DOMESTIC,
                'is_default' => true,
                'is_active' => true,
                'file_path' => 'c.docx',
            ],
        ]);

        $eligibility = app(PrintFormTemplateOrderEligibility::class);

        $preferred = $eligibility->preferOwnCompanySpecificTemplates($templates, $companyAId);

        $this->assertCount(1, $preferred);
        $this->assertSame(2, $preferred->first()['id']);
    }

    public function test_resolve_default_template_prefers_company_specific_default(): void
    {
        $companyAId = 101;

        $templates = collect([
            [
                'id' => 1,
                'entity_type' => 'order',
                'party' => 'customer',
                'own_company_id' => null,
                'transport_scope' => PrintFormTemplateTransportScope::DOMESTIC,
                'is_default' => true,
                'is_active' => true,
                'file_path' => 'universal.docx',
            ],
            [
                'id' => 2,
                'entity_type' => 'order',
                'party' => 'customer',
                'own_company_id' => $companyAId,
                'transport_scope' => PrintFormTemplateTransportScope::DOMESTIC,
                'is_default' => true,
                'is_active' => true,
                'file_path' => 'company-a.docx',
            ],
        ]);

        $eligibility = app(PrintFormTemplateOrderEligibility::class);

        $default = $eligibility->resolveDefaultTemplate(
            $templates,
            $companyAId,
            false,
            'customer',
        );

        $this->assertIsArray($default);
        $this->assertSame(2, $default['id']);
    }
}
