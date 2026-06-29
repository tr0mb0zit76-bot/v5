<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\ProposalHtmlTemplate;
use App\Models\Role;
use App\Models\User;
use App\Services\Commercial\LeadProposalPdfService;
use Database\Seeders\ProposalHtmlTemplateVariableSeeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class LeadProposalHtmlTemplateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('proposal_html_templates') || ! Schema::hasTable('leads')) {
            $this->markTestSkipped('Proposal HTML or lead tables unavailable.');
        }

        $this->seed(ProposalHtmlTemplateVariableSeeder::class);
    }

    public function test_manager_can_generate_pdf_offer_from_html_template(): void
    {
        Storage::fake('local');

        $pdfService = Mockery::mock(LeadProposalPdfService::class);
        $pdfService->shouldReceive('convertHtmlToPdf')
            ->once()
            ->andReturn('%PDF-1.4 test');
        $this->app->instance(LeadProposalPdfService::class, $pdfService);

        $role = Role::query()->firstOrCreate(
            ['name' => 'manager-html-cp'],
            [
                'display_name' => 'Менеджер',
                'visibility_areas' => ['leads'],
                'visibility_scopes' => ['leads' => 'own'],
            ],
        );

        $user = User::factory()->create(['role_id' => $role->id]);
        $lead = Lead::factory()->create(['responsible_id' => $user->id]);
        $template = ProposalHtmlTemplate::factory()->create([
            'owner_user_id' => $user->id,
            'html_body' => '<h1>КП {lead.number}</h1>',
        ]);

        $response = $this->actingAs($user)->post(route('leads.proposal.from-html-template', $lead), [
            'proposal_html_template_id' => $template->id,
        ]);

        $response->assertRedirect(route('leads.show', $lead));

        $offer = $lead->offers()->latest('id')->first();
        $this->assertNotNull($offer);
        $this->assertNotNull($offer->generated_file_path);
        $this->assertSame('html_template', data_get($offer->payload, 'source'));
        $this->assertSame($template->id, data_get($offer->payload, 'proposal_html_template_id'));
        $this->assertSame('application/pdf', data_get($offer->payload, 'content_type'));
        Storage::disk('local')->assertExists((string) $offer->generated_file_path);
    }

    public function test_html_preview_returns_rendered_document(): void
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'manager-html-preview'],
            [
                'display_name' => 'Менеджер',
                'visibility_areas' => ['leads'],
                'visibility_scopes' => ['leads' => 'own'],
            ],
        );

        $user = User::factory()->create(['role_id' => $role->id]);
        $lead = Lead::factory()->create([
            'responsible_id' => $user->id,
            'number' => 'L-PREV',
        ]);
        $template = ProposalHtmlTemplate::factory()->create([
            'html_body' => '<p>Номер: {lead.number}</p>',
        ]);

        $response = $this->actingAs($user)->get(route('leads.proposal.html-preview', [
            'lead' => $lead,
            'proposal_html_template_id' => $template->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/html; charset=UTF-8');
        $this->assertStringContainsString('L-PREV', (string) $response->getContent());
    }

    public function test_settings_user_can_open_template_editor_index(): void
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'admin-html-templates'],
            [
                'display_name' => 'Админ',
                'visibility_areas' => ['settings', 'leads', 'modules_proposal_templates'],
                'visibility_scopes' => ['leads' => 'all'],
            ],
        );

        $user = User::factory()->create(['role_id' => $role->id]);
        ProposalHtmlTemplate::factory()->count(2)->create(['owner_user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('modules.proposal-templates.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Modules/ProposalTemplates/Index')
            ->has('templates', 2));
    }

    public function test_settings_user_can_open_grapes_editor_create_page(): void
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'admin-html-templates-editor'],
            [
                'display_name' => 'Админ',
                'visibility_areas' => ['settings', 'modules_proposal_templates'],
                'visibility_scopes' => ['leads' => 'all'],
            ],
        );

        $user = User::factory()->create(['role_id' => $role->id]);
        Lead::factory()->create(['responsible_id' => $user->id, 'number' => 'L-GRAPES']);

        $response = $this->actingAs($user)->get(route('modules.proposal-templates.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Modules/ProposalTemplates/Editor')
            ->where('template', null)
            ->has('variables')
            ->has('previewLeads'));
    }
}
