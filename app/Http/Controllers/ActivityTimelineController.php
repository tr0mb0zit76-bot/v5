<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\ActivityLedgerService;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityTimelineController extends Controller
{
    public function __construct(
        private readonly ActivityLedgerService $activityLedger,
    ) {}

    public function showForLead(Request $request, Lead $lead): JsonResponse
    {
        abort_unless($this->canAccessLead($request, $lead), 403);

        $this->activityLedger->backfillFromLeadActivities($lead);

        return response()->json([
            'events' => $this->activityLedger->timelineForSubject($lead)->values()->all(),
        ]);
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
