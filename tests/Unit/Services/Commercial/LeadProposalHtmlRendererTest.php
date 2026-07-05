<?php

namespace Tests\Unit\Services\Commercial;

use App\Models\Lead;
use App\Models\ProposalHtmlTemplate;
use App\Services\Commercial\LeadProposalHtmlRenderer;
use App\Services\LeadPrintFormDraftService;
use App\Support\ProposalHtmlTemplateColdEmailLibrary;
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

    public function test_renders_cold_email_template_with_sender_and_local_assets(): void
    {
        if (! Schema::hasTable('leads')) {
            $this->markTestSkipped('Lead tables unavailable.');
        }

        $lead = Lead::factory()->create([
            'number' => 'L-COLD',
            'title' => 'Холодная рассылка',
        ]);

        $snapshot = [
            'counterparty' => ['contact_person' => 'Иван'],
            'responsible' => [
                'name' => 'Анна Менеджер',
                'phone' => '+7 900 000-00-00',
                'email' => 'sales@example.test',
            ],
        ];

        $draftService = Mockery::mock(LeadPrintFormDraftService::class);
        $draftService->shouldReceive('buildLeadSnapshot')
            ->once()
            ->with(Mockery::on(fn (Lead $passedLead): bool => $passedLead->is($lead)))
            ->andReturn($snapshot);

        $renderer = new LeadProposalHtmlRenderer($draftService);
        $templateDefinition = ProposalHtmlTemplateColdEmailLibrary::templateBySlug('aa-heavy-equipment-cold');

        $template = new ProposalHtmlTemplate([
            'html_body' => $templateDefinition['html_body'],
            'css_inline' => $templateDefinition['css_inline'],
        ]);

        $rendered = $renderer->render($template, $lead);

        $this->assertStringContainsString('Автоальянс-Смоленск', $rendered['html']);
        $this->assertStringContainsString('Добрый день, <strong style="color:#de3b3b;">Иван</strong>.', $rendered['html']);
        $this->assertStringContainsString('Анна Менеджер', $rendered['html']);
        $this->assertStringContainsString('/assets/proposal-emails/heavy-equipment.svg', $rendered['html']);
        $this->assertStringNotContainsString('img.hiteml.com', $rendered['html']);
        $this->assertStringNotContainsString('МЕНЯЕМ_ИМЯ', $rendered['html']);
    }
}
