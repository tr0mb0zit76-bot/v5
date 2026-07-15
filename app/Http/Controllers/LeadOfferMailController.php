<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendLeadOfferMailRequest;
use App\Models\Contractor;
use App\Models\Lead;
use App\Models\LeadOffer;
use App\Services\Commercial\ProposalHtmlCidMailPreparer;
use App\Services\CommercialMailService;
use App\Support\LeadViewAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeadOfferMailController extends Controller
{
    public function __construct(
        private readonly CommercialMailService $commercialMail,
        private readonly ProposalHtmlCidMailPreparer $cidMailPreparer,
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
            ? 'КП отправлено: HTML-затравка в письме'.($attachments !== [] ? ' + PDF во вложении' : '').'.'
            : 'Коммерческое предложение отправлено.';

        return back()->with('flash', [
            'type' => 'success',
            'message' => $message,
        ]);
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
