<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Models\PrintFormBasicTerm;
use App\Models\PrintFormTemplate;
use App\Services\PrintForm\ContractorPrintFormProfileResolver;
use Tests\TestCase;

class ContractorPrintFormProfileResolverTest extends TestCase
{
    public function test_resolver_returns_internal_standard_by_default(): void
    {
        $contractor = Contractor::query()->create(['name' => 'ООО Тест']);

        $profile = app(ContractorPrintFormProfileResolver::class)->resolve($contractor);

        $this->assertSame(ContractorPrintFormProfileResolver::MODE_INTERNAL_STANDARD, $profile['mode']);
        $this->assertSame('Стандартная', $profile['label']);
    }

    public function test_resolver_detects_customized_basic_terms(): void
    {
        $contractor = Contractor::query()->create(['name' => 'ООО Правки']);

        PrintFormBasicTerm::query()->create([
            'party' => PrintFormBasicTerm::PARTY_CARRIER,
            'contractor_id' => $contractor->id,
            'sort_order' => 1,
            'body' => 'Особый пункт',
        ]);

        $profile = app(ContractorPrintFormProfileResolver::class)->resolve($contractor);

        $this->assertSame(ContractorPrintFormProfileResolver::MODE_INTERNAL_CUSTOMIZED, $profile['mode']);
        $this->assertSame('Наша с правками', $profile['carrier']['label']);
    }

    public function test_resolver_detects_external_docx_template(): void
    {
        $contractor = Contractor::query()->create(['name' => 'ООО Внешняя']);

        PrintFormTemplate::query()->create([
            'code' => 'ext_carrier_'.$contractor->id,
            'name' => 'Форма перевозчика',
            'entity_type' => 'order',
            'document_type' => 'contract_request',
            'document_group' => 'contractual',
            'party' => PrintFormBasicTerm::PARTY_CARRIER,
            'source_type' => 'external_docx',
            'vue_component' => 'ExternalDocxPrintFormTemplate',
            'contractor_id' => $contractor->id,
            'is_active' => true,
        ]);

        $profile = app(ContractorPrintFormProfileResolver::class)->resolve($contractor);

        $this->assertSame(ContractorPrintFormProfileResolver::MODE_CONTRACTOR_EXTERNAL, $profile['mode']);
        $this->assertSame('Форма контрагента', $profile['carrier']['label']);
    }
}
