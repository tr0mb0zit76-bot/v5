<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Models\Lead;
use App\Services\DocxPlaceholderExtractor;
use App\Services\LeadPrintFormDraftService;
use App\Services\PrintFormVariableCatalog;
use App\Support\PrintFormPlaceholderPathResolver;
use Illuminate\Support\Collection;
use Tests\TestCase;

class LeadPrintFormDraftServiceTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function buildSnapshot(LeadPrintFormDraftService $service, Lead $lead): array
    {
        $method = new \ReflectionMethod($service, 'buildSnapshot');
        $method->setAccessible(true);

        /** @var array<string, mixed> $snapshot */
        $snapshot = $method->invoke($service, $lead);

        return $snapshot;
    }

    public function test_counterparty_postal_address_and_signer_position_are_exposed_for_print_forms(): void
    {
        $service = new LeadPrintFormDraftService(new DocxPlaceholderExtractor, new PrintFormPlaceholderPathResolver);
        $lead = new Lead;
        $counterparty = new Contractor([
            'name' => 'ООО Контрагент',
            'postal_address' => '620000, Екатеринбург, а/я 3',
            'contact_person_position' => 'Директор по закупкам',
            'signer_position' => 'Управляющий директор',
        ]);

        $lead->setRelation('counterparty', $counterparty);
        $lead->setRelation('responsible', null);
        $lead->setRelation('routePoints', new Collection);
        $lead->setRelation('cargoItems', new Collection);
        $lead->setRelation('offers', new Collection);

        $snapshot = $this->buildSnapshot($service, $lead);
        $catalogValues = collect((new PrintFormVariableCatalog)->leadOptions())
            ->pluck('value')
            ->all();

        $this->assertSame('620000, Екатеринбург, а/я 3', data_get($snapshot, 'counterparty.postal_address'));
        $this->assertSame('Управляющий директор', data_get($snapshot, 'counterparty.signer_position'));
        $this->assertContains('counterparty.postal_address', $catalogValues);
        $this->assertContains('counterparty.signer_position', $catalogValues);
    }
}
