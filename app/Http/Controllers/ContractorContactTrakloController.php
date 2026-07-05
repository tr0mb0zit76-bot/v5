<?php

namespace App\Http\Controllers;

use App\Http\Requests\External\ProvisionExternalUserInviteRequest;
use App\Models\Contractor;
use App\Models\ContractorContact;
use App\Services\ExternalUsers\ExternalUserProvisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractorContactTrakloController extends Controller
{
    public function __construct(
        private readonly ExternalUserProvisionService $provisionService,
    ) {}

    public function setPrimary(Request $request, Contractor $contractor, ContractorContact $contact): JsonResponse
    {
        abort_unless($request->user() !== null, 403);

        $this->assertContactBelongs($contractor, $contact);

        $this->provisionService->setTrakloPrimary($contractor, $contact);

        return response()->json([
            'contact_id' => $contact->id,
            'is_traklo_primary' => true,
        ]);
    }

    public function invite(ProvisionExternalUserInviteRequest $request, Contractor $contractor, ContractorContact $contact): JsonResponse
    {
        $this->assertContactBelongs($contractor, $contact);

        $payload = $this->provisionService->provisionInvite(
            $contractor,
            $contact,
            $request->user(),
            $request->validated('external_party'),
        );

        return response()->json([
            'user_id' => $payload['user']->id,
            'created' => $payload['created'],
            'url' => $payload['url'],
            'expires_at' => $payload['invite']->expires_at?->toIso8601String(),
            'traklo_apk_url' => config('external_users.apk_url', '/downloads/traklo.apk'),
        ]);
    }

    private function assertContactBelongs(Contractor $contractor, ContractorContact $contact): void
    {
        abort_unless((int) $contact->contractor_id === (int) $contractor->id, 404);
    }
}
