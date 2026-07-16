<?php

namespace Tests\Feature;

use App\Mail\CommercialOutboundMail;
use App\Models\Lead;
use App\Models\ProposalHtmlTemplate;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ProposalHtmlTemplateVariableSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeadHtmlProposalMailSendTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (
            ! Schema::hasTable('proposal_html_templates')
            || ! Schema::hasTable('leads')
            || ! Schema::hasTable('mail_threads')
            || ! Schema::hasTable('lead_offers')
        ) {
            $this->markTestSkipped('HTML-КП или почтовые таблицы недоступны.');
        }

        $this->seed(ProposalHtmlTemplateVariableSeeder::class);
    }

    #[Test]
    public function send_html_email_creates_offer_without_pdf_and_sends_html_body(): void
    {
        Mail::fake();

        $role = Role::query()->firstOrCreate(
            ['name' => 'manager-html-send'],
            [
                'display_name' => 'Менеджер',
                'visibility_areas' => ['leads', 'mail'],
                'visibility_scopes' => ['leads' => 'own'],
            ],
        );

        $user = User::factory()->create(['role_id' => $role->id]);
        $lead = Lead::factory()->create([
            'responsible_id' => $user->id,
            'number' => 'L-HTML-SEND',
            'title' => 'Перевозка тест',
        ]);
        $template = ProposalHtmlTemplate::factory()->create([
            'owner_user_id' => $user->id,
            'name' => 'Параллельный импорт',
            'html_body' => '<p>КП {lead.number}</p>',
            'email_assets' => [],
        ]);

        $response = $this->actingAs($user)->post(route('leads.proposal.send-html-email', $lead), [
            'proposal_html_template_id' => $template->id,
            'to' => ['client@example.com'],
            'subject' => 'КП по перевозке',
            'body' => "Добрый день!\n\nНаправляем коммерческое предложение.",
        ]);

        $response->assertRedirect();

        $offer = $lead->offers()->latest('id')->first();
        $this->assertNotNull($offer);
        $this->assertNull($offer->generated_file_path);
        $this->assertSame('sent', $offer->status);
        $this->assertNotNull($offer->sent_at);
        $this->assertSame('html_template', data_get($offer->payload, 'source'));
        $this->assertStringContainsString('L-HTML-SEND', (string) data_get($offer->payload, 'rendered_html'));

        Mail::assertSent(CommercialOutboundMail::class, function (CommercialOutboundMail $mail): bool {
            return is_string($mail->bodyHtml)
                && str_contains($mail->bodyHtml, 'L-HTML-SEND')
                && $mail->outboundAttachments === []
                && $mail->hasTo('client@example.com');
        });
    }

    #[Test]
    public function send_html_email_forbidden_for_foreign_lead(): void
    {
        Mail::fake();

        $role = Role::query()->firstOrCreate(
            ['name' => 'manager-html-send-deny'],
            [
                'display_name' => 'Менеджер',
                'visibility_areas' => ['leads'],
                'visibility_scopes' => ['leads' => 'own'],
            ],
        );

        $owner = User::factory()->create(['role_id' => $role->id]);
        $stranger = User::factory()->create(['role_id' => $role->id]);
        $lead = Lead::factory()->create(['responsible_id' => $owner->id]);
        $template = ProposalHtmlTemplate::factory()->create([
            'html_body' => '<p>x</p>',
        ]);

        $response = $this->actingAs($stranger)->post(route('leads.proposal.send-html-email', $lead), [
            'proposal_html_template_id' => $template->id,
            'to' => ['client@example.com'],
            'subject' => 'КП',
            'body' => 'Текст',
        ]);

        $response->assertForbidden();
        Mail::assertNothingSent();
    }
}
