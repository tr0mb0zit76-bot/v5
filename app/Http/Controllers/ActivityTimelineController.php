<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Order;
use App\Services\ActivityLedgerService;
use App\Services\OrderActivityTimelineService;
use App\Support\LeadViewAuthorization;
use App\Support\OrderViewAuthorization;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityTimelineController extends Controller
{
    public function __construct(
        private readonly ActivityLedgerService $activityLedger,
        private readonly OrderActivityTimelineService $orderActivityTimeline,
    ) {}

    public function showForLead(Request $request, Lead $lead): JsonResponse
    {
        abort_unless($this->canAccessLead($request, $lead), 403);

        $this->activityLedger->backfillFromLeadActivities($lead);

        return response()->json([
            'events' => $this->activityLedger->timelineForSubject($lead)->values()->all(),
        ]);
    }

    public function showForOrder(Request $request, Order $order): JsonResponse
    {
        abort_unless($this->canAccessOrder($request, $order), 403);

        return response()->json([
            'events' => $this->orderActivityTimeline->timelineForOrder($order),
        ]);
    }

    private function canAccessOrder(Request $request, Order $order): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if ($user->isAdmin() || $user->isSupervisor()) {
            return true;
        }

        if (! RoleAccess::canAccessVisibilityArea($user, 'orders')) {
            return false;
        }

        return OrderViewAuthorization::userCanViewOrder($user, $order);
    }

    private function canAccessLead(Request $request, Lead $lead): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if ($user->isAdmin() || $user->isSupervisor()) {
            return true;
        }

        return LeadViewAuthorization::userCanViewLead($user, $lead);
    }
}
