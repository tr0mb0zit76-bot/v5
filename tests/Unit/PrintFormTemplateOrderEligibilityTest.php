<?php

namespace Tests\Unit;

use App\Services\PrintFormTemplateOrderEligibility;
use App\Support\PrintFormTemplateTransportScope;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PrintFormTemplateOrderEligibilityTest extends TestCase
{
    private PrintFormTemplateOrderEligibility $eligibility;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eligibility = new PrintFormTemplateOrderEligibility;
    }

    /**
     * @param  array<string, mixed>  $template
     */
    #[DataProvider('templateAvailabilityProvider')]
    public function test_template_availability_for_context(
        array $template,
        ?int $ownCompanyId,
        bool $isInternational,
        ?string $party,
        array $contractorIds,
        bool $expected,
    ): void {
        $this->assertSame(
            $expected,
            $this->eligibility->isArrayTemplateAvailableForContext(
                $template,
                $ownCompanyId,
                $isInternational,
                $party,
                $contractorIds,
            ),
        );
    }

    /**
     * @return list<array{0: array<string, mixed>, 1: ?int, 2: bool, 3: ?string, 4: list<int>, 5: bool}>
     */
    public static function templateAvailabilityProvider(): array
    {
        $base = [
            'entity_type' => 'order',
            'is_active' => true,
            'file_path' => 'templates/test.docx',
            'party' => 'customer',
            'contractor_id' => null,
            'own_company_id' => null,
            'transport_scope' => PrintFormTemplateTransportScope::ANY,
        ];

        return [
            'generic domestic customer template matches domestic order' => [
                $base,
                10,
                false,
                'customer',
                [],
                true,
            ],
            'own company mismatch rejects template' => [
                array_merge($base, ['own_company_id' => 5]),
                10,
                false,
                'customer',
                [],
                false,
            ],
            'own company match accepts template' => [
                array_merge($base, ['own_company_id' => 10]),
                10,
                false,
                'customer',
                [],
                true,
            ],
            'domestic scope rejects international order' => [
                array_merge($base, ['transport_scope' => PrintFormTemplateTransportScope::DOMESTIC]),
                null,
                true,
                'customer',
                [],
                false,
            ],
            'international scope rejects domestic order' => [
                array_merge($base, ['transport_scope' => PrintFormTemplateTransportScope::INTERNATIONAL]),
                null,
                false,
                'customer',
                [],
                false,
            ],
            'carrier party mismatch rejects customer-only template' => [
                array_merge($base, ['party' => 'customer']),
                null,
                false,
                'carrier',
                [],
                false,
            ],
            'internal party template matches carrier slot' => [
                array_merge($base, ['party' => 'internal']),
                null,
                false,
                'carrier',
                [],
                true,
            ],
            'contractor-specific template requires contractor in order' => [
                array_merge($base, ['contractor_id' => 42]),
                null,
                false,
                'customer',
                [99],
                false,
            ],
        ];
    }

    public function test_resolve_default_template_prefers_scoped_default(): void
    {
        $templates = collect([
            [
                'id' => 1,
                'name' => 'Generic',
                'entity_type' => 'order',
                'is_active' => true,
                'file_path' => 'a.docx',
                'party' => 'customer',
                'contractor_id' => null,
                'own_company_id' => null,
                'transport_scope' => PrintFormTemplateTransportScope::ANY,
                'is_default' => true,
            ],
            [
                'id' => 2,
                'name' => 'Company domestic',
                'entity_type' => 'order',
                'is_active' => true,
                'file_path' => 'b.docx',
                'party' => 'customer',
                'contractor_id' => null,
                'own_company_id' => 7,
                'transport_scope' => PrintFormTemplateTransportScope::DOMESTIC,
                'is_default' => true,
            ],
        ]);

        $resolved = $this->eligibility->resolveDefaultTemplate(
            $templates,
            7,
            false,
            'customer',
            [],
        );

        $this->assertIsArray($resolved);
        $this->assertSame(2, $resolved['id']);
    }

    public function test_resolve_default_template_returns_null_when_none_match(): void
    {
        $templates = Collection::make([
            [
                'id' => 3,
                'entity_type' => 'order',
                'is_active' => true,
                'file_path' => 'c.docx',
                'party' => 'customer',
                'contractor_id' => null,
                'own_company_id' => 99,
                'transport_scope' => PrintFormTemplateTransportScope::INTERNATIONAL,
                'is_default' => true,
            ],
        ]);

        $this->assertNull(
            $this->eligibility->resolveDefaultTemplate($templates, 7, false, 'customer', []),
        );
    }
}
