<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Services\Mobile\MobileShellFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileShellController extends Controller
{
    public function __construct(
        private MobileShellFeedService $mobileShellFeedService,
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
}
