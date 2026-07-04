<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Mobile\MobileEntityChipService;
use App\Services\Mobile\MobileShellFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileShellController extends Controller
{
    public function __construct(
        private MobileShellFeedService $mobileShellFeedService,
        private MobileEntityChipService $mobileEntityChipService,
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
}
