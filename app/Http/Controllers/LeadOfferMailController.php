<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendLeadOfferMailRequest;
use App\Models\Contractor;
use App\Models\Lead;
use App\Models\LeadOffer;
use App\Services\CommercialMailService;
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeadOfferMailController extends Controller
{
    public function __construct(
        private readonly CommercialMailService $commercialMail,
    ) {}

    public function send(SendLeadOfferMailRequest $request, Lead $lead, LeadOffer $offer): RedirectResponse
    {
        abort_unless((int) $offer->lead_id === (int) $lead->id, 404);
        abort_unless($this->canAccessLead($request, $lead), 403);

        $attachment = $this->commercialMail->resolveOfferAttachment($offer);

        $this->commercialMail->sendOutbound(
            subject: $request->string('subject')->toString(),
            bodyText: $request->string('body')->toString(),
            toEmails: $request->input('to', []),
            sender: $request->user(),
            lead: $lead,
            offer: $offer,
            ccEmails: $request->input('cc', []),
            attachmentPath: $attachment['path'] ?? null,
            attachmentName: $attachment['name'] ?? null,
            attachmentDriver: $attachment['driver'] ?? null,
        );

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Коммерческое предложение отправлено.',
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

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'leads');

        return $scope === 'all' || (int) $lead->responsible_id === (int) $user->id;
    }
}
