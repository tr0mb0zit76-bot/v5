<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\UpdateMobileLeadDraftRequest;
use App\Models\Contractor;
use App\Models\Lead;
use App\Models\Order;
use App\Services\LeadMessageIntakeService;
use App\Services\Mobile\MobileEntityChipService;
use App\Services\Mobile\MobileShellFeedService;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class MobileShellController extends Controller
{
    public function __construct(
        private MobileShellFeedService $mobileShellFeedService,
        private MobileEntityChipService $mobileEntityChipService,
        private LeadMessageIntakeService $leadMessageIntakeService,
    ) {}

    public function tasks(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        return response()->json(
            $this->mobileShellFeedService->tasksForUser($user, $validated['q'] ?? null),
        );
    }

    public function orders(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        return response()->json(
            $this->mobileShellFeedService->ordersForUser($user, $validated['q'] ?? null),
        );
    }

    public function documents(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        return response()->json(
            $this->mobileShellFeedService->documentsForUser($user, $validated['q'] ?? null),
        );
    }

    public function documentContractors(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        return response()->json(
            $this->mobileShellFeedService->documentContractorsForUser($user, $validated['q'] ?? null),
        );
    }

    public function documentContractorOrders(Request $request, Contractor $contractor): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        return response()->json(
            $this->mobileShellFeedService->documentOrdersForContractor($user, $contractor, $validated['q'] ?? null),
        );
    }

    public function orderDocumentChecklist(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return response()->json(
            $this->mobileShellFeedService->orderDocumentChecklistForUser($user, $order),
        );
    }

    public function trakloLeads(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        return response()->json(
            $this->mobileShellFeedService->trakloLeadsForUser($user, $validated['q'] ?? null),
        );
    }

    public function createLeadFromText(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless(
            Schema::hasTable('leads') && RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'leads'),
            403,
        );

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:10', 'max:4000'],
        ]);

        $result = $this->leadMessageIntakeService->createFromText($validated['message'], $user);
        $lead = $result['lead'];

        return response()->json([
            'lead' => [
                'id' => (int) $lead->id,
                'number' => $lead->number,
                'title' => $lead->title,
                'loading_location' => $lead->loading_location,
                'unloading_location' => $lead->unloading_location,
            ],
            'parsed' => $result['parsed'],
            'warnings' => $result['warnings'],
        ], 201);
    }

    public function updateLeadDraft(UpdateMobileLeadDraftRequest $request, Lead $lead): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless(
            Schema::hasTable('leads') && RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'leads'),
            403,
        );

        return response()->json(
            $this->mobileShellFeedService->updateLeadDraftForUser($user, $lead, $request->validated()),
        );
    }

    public function entityChips(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'kind' => ['sometimes', 'nullable', 'string', Rule::in(['document', 'order', 'lead', 'contractor'])],
        ]);

        return response()->json(
            $this->mobileEntityChipService->search(
                $user,
                $validated['q'] ?? null,
                $validated['kind'] ?? null,
            ),
        );
    }

    public function orderDocumentSlots(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return response()->json(
            $this->mobileShellFeedService->orderDocumentUploadOptions($user, $order),
        );
    }

    public function orderSummary(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return response()->json(
            $this->mobileShellFeedService->orderSummaryForUser($user, $order),
        );
    }

    public function leadSummary(Request $request, Lead $lead): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return response()->json(
            $this->mobileShellFeedService->leadSummaryForUser($user, $lead),
        );
    }

    public function contractorSummary(Request $request, Contractor $contractor): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return response()->json(
            $this->mobileShellFeedService->contractorSummaryForUser($user, $contractor),
        );
    }

    public function linkPreview(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
        ]);

        $preview = $this->mobileShellFeedService->linkPreviewForUser($user, $validated['url']);

        return response()->json([
            'preview' => $preview,
        ]);
    }
}
