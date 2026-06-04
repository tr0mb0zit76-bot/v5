<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExtractOrderIntakeRequest;
use App\Services\Mcp\OrderIntakeMcpService;
use App\Services\Orders\OrderDocumentIntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderIntakeController extends Controller
{
    public function extract(
        ExtractOrderIntakeRequest $request,
        OrderDocumentIntakeService $intakeService,
    ): JsonResponse {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $file = $request->file('file');
        if ($file === null) {
            throw ValidationException::withMessages([
                'file' => 'Выберите файл заявки.',
            ]);
        }

        return response()->json(
            $intakeService->extractFromUpload($user, $file),
        );
    }

    public function drafts(Request $request, OrderIntakeMcpService $intakeMcp): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $limit = (int) $request->query('limit', 10);

        return response()->json([
            'drafts' => $intakeMcp->listRecentDrafts($user, max(1, min($limit, 25))),
        ]);
    }

    public function show(Request $request, int $draft, OrderIntakeMcpService $intakeMcp): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        return response()->json($intakeMcp->getDraft($user, $draft));
    }

    public function activateLearning(Request $request, int $draft, OrderIntakeMcpService $intakeMcp): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $intakeMcp->activateDraftForLearning($user, $draft);

        return response()->json(['ok' => true]);
    }

    public function discardLearning(Request $request, int $draft, OrderIntakeMcpService $intakeMcp): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        return response()->json([
            'ok' => $intakeMcp->discardDraftLearning($user, $draft),
        ]);
    }
}
