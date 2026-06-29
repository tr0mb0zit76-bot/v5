<?php

namespace Tests\Unit\Services\Commercial;

use App\Models\Lead;
use App\Models\ProposalHtmlTemplate;
use App\Services\Commercial\LeadProposalHtmlRenderer;
use App\Services\LeadPrintFormDraftService;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class LeadProposalHtmlRendererTest extends TestCase
{
    public function test_replaces_lead_placeholders_in_html_body(): void
    {
        if (! Schema::hasTable('leads')) {
            $this->markTestSkipped('Lead tables unavailable.');
        }

        $lead = Lead::factory()->create([
            'number' => 'L-100',
            'title' => 'Тестовый лид',
        ]);

        $snapshot = [
            'lead' => ['number' => 'L-100', 'title' => 'Тестовый лид'],
            'counterparty' => ['name' => 'ООО Ромашка'],
            'offer' => ['price' => '10 000,00', 'currency' => 'RUB'],
        ];

        $draftService = Mockery::mock(LeadPrintFormDraftService::class);
        $draftService->shouldReceive('buildLeadSnapshot')
            ->once()
            ->with(Mockery::on(fn (Lead $passedLead): bool => $passedLead->is($lead)))
            ->andReturn($snapshot);

        $renderer = new LeadProposalHtmlRenderer($draftService);

        $template = new ProposalHtmlTemplate([
            'html_body' => '<p>{lead.number}: {counterparty.name} — {offer.price} {offer.currency}</p>',
            'css_inline' => 'p{color:#000}',
        ]);

        $rendered = $renderer->render($template, $lead);

        $this->assertStringContainsString('L-100: ООО Ромашка — 10 000,00 RUB', $rendered['html']);
        $this->assertStringContainsString('<style>p{color:#000}</style>', $rendered['html']);
    }
}
