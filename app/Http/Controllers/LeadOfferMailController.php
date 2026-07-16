<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendLeadHtmlProposalMailRequest;
use App\Http\Requests\SendLeadOfferMailRequest;
use App\Models\Contractor;
use App\Models\Lead;
use App\Models\LeadOffer;
use App\Models\ProposalHtmlTemplate;
use App\Models\User;
use App\Services\Commercial\LeadProposalHtmlRenderer;
use App\Services\Commercial\ProposalHtmlCidMailPreparer;
use App\Services\CommercialMailService;
use App\Support\LeadViewAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class LeadOfferMailController extends Controller
{
    public function __construct(
        private readonly CommercialMailService $commercialMail,
        private readonly ProposalHtmlCidMailPreparer $cidMailPreparer,
        private readonly LeadProposalHtmlRenderer $htmlRenderer,
    ) {}

    public function send(SendLeadOfferMailRequest $request, Lead $lead, LeadOffer $offer): RedirectResponse
    {
        abort_unless((int) $offer->lead_id === (int) $lead->id, 404);
        abort_unless($this->canAccessLead($request, $lead), 403);

        $attachments = [];
        $offerAttachment = $this->commercialMail->resolveOfferAttachment($offer);

        if ($offerAttachment !== null) {
            $attachments[] = [
                'path' => $offerAttachment['path'],
                'name' => $offerAttachment['name'],
                'driver' => $offerAttachment['driver'],
                'mime_type' => $offerAttachment['mime_type'] ?? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ];
        }

        $bodyHtml = null;
        $inlineImages = [];
        $payload = is_array($offer->payload) ? $offer->payload : [];
        $useHtmlTeaser = $request->boolean('use_html_teaser', true);
        $renderedHtml = trim((string) ($payload['rendered_html'] ?? ''));
        $emailAssets = is_array($payload['email_assets'] ?? null) ? $payload['email_assets'] : [];

        if ($useHtmlTeaser && $renderedHtml !== '') {
            $prepared = $this->cidMailPreparer->prepare($renderedHtml, $emailAssets);
            $bodyHtml = $prepared['html'];
            $inlineImages = $prepared['embeds'];
        }

        $this->commercialMail->sendOutbound(
            subject: $request->string('subject')->toString(),
            bodyText: $request->string('body')->toString(),
            toEmails: $request->input('to', []),
            sender: $request->user(),
            lead: $lead,
            offer: $offer,
            ccEmails: $request->input('cc', []),
            attachments: $attachments,
            bodyHtml: $bodyHtml,
            inlineImages: $inlineImages,
        );

        $message = $bodyHtml !== null
            ? 'КП отправлено: HTML в теле письма'.($attachments !== [] ? ' + файл во вложении' : '').'.'
            : 'Коммерческое предложение отправлено.';

        return back()->with('flash', [
            'type' => 'success',
            'message' => $message,
        ]);
    }

    public function sendHtml(SendLeadHtmlProposalMailRequest $request, Lead $lead): RedirectResponse
    {
        abort_unless(Schema::hasTable('proposal_html_templates') && Schema::hasTable('lead_offers'), 404);
        abort_unless($this->canAccessLead($request, $lead), 403);

        $template = ProposalHtmlTemplate::query()
            ->where('is_active', true)
            ->findOrFail($request->integer('proposal_html_template_id'));

        $rendered = $this->htmlRenderer->render($template, $lead);
        $emailAssets = is_array($template->email_assets) ? $template->email_assets : [];
        $prepared = $this->cidMailPreparer->prepare($rendered['html'], $emailAssets);

        $offer = $this->prepareOfferForHtmlSend($lead, $request->user(), $template, $rendered['html'], $emailAssets);

        $this->commercialMail->sendOutbound(
            subject: $request->string('subject')->toString(),
            bodyText: $request->string('body')->toString(),
            toEmails: $request->input('to', []),
            sender: $request->user(),
            lead: $lead,
            offer: $offer,
            ccEmails: $request->input('cc', []),
            attachments: [],
            bodyHtml: $prepared['html'],
            inlineImages: $prepared['embeds'],
        );

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'HTML-КП отправлено по e-mail.',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $emailAssets
     */
    private function prepareOfferForHtmlSend(
        Lead $lead,
        ?User $user,
        ProposalHtmlTemplate $template,
        string $renderedHtml,
        array $emailAssets,
    ): LeadOffer {
        $basePayload = [
            'title' => $lead->title,
            'description' => $lead->description,
            'target_price' => $lead->target_price,
            'target_currency' => $lead->target_currency,
            'route' => [
                'loading_location' => $lead->loading_location,
                'unloading_location' => $lead->unloading_location,
            ],
            'source' => 'html_template',
            'proposal_html_template_id' => $template->id,
            'proposal_html_template_name' => $template->name,
            'rendered_html' => $renderedHtml,
            'email_assets' => $emailAssets,
            'has_html_teaser' => true,
        ];

        $offer = $lead->offers()
            ->whereNull('sent_at')
            ->latest('id')
            ->first();

        if ($offer === null) {
            return $lead->offers()->create([
                'status' => 'prepared',
                'number' => 'КП-'.$lead->number,
                'title' => $lead->title,
                'offer_date' => now()->toDateString(),
                'price' => $lead->target_price,
                'currency' => $lead->target_currency ?: 'RUB',
                'payload' => $basePayload,
                'created_by' => $user?->id,
            ]);
        }

        $existingPayload = is_array($offer->payload) ? $offer->payload : [];
        $offer->update([
            'status' => 'prepared',
            'title' => $lead->title,
            'offer_date' => now()->toDateString(),
            'price' => $lead->target_price,
            'currency' => $lead->target_currency ?: 'RUB',
            'payload' => array_merge($existingPayload, $basePayload),
        ]);

        return $offer->refresh();
    }

    /**
     * @return list<string>
     */
    public static function defaultRecipientEmails(Lead $lead): array
    {
        $contractor = $lead->counterparty_id
            ? Contractor::query()->find($lead->counterparty_id)
            : null;

        $emails = array_filter([
            $contractor?->contact_person_email,
            $contractor?->email,
        ]);

        return array_values(array_unique($emails));
    }

    private function canAccessLead(Request $request, Lead $lead): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return LeadViewAuthorization::userCanViewLead($user, $lead);
    }
}
