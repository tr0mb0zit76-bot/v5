<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGridViewRequest;
use App\Http\Requests\UpdateGridViewRequest;
use App\Models\GridView;
use App\Services\GridViewService;
use App\Support\GridViewCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GridViewController extends Controller
{
    public function __construct(
        private readonly GridViewService $gridViews,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $gridKey = (string) $request->string('grid_key');
        abort_unless(GridViewCatalog::isValidGridKey($gridKey), 404);

        $views = $this->gridViews
            ->listForGrid($user, $gridKey)
            ->map(fn (GridView $view): array => $this->gridViews->serialize($view, $user));

        return response()->json([
            'views' => $views,
            'can_share' => $this->gridViews->userCanShare($user),
            'share_options' => $this->gridViews->userCanShare($user)
                ? $this->gridViews->shareOptionsFor($user)
                : null,
        ]);
    }

    public function show(Request $request, GridView $gridView): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless($this->gridViews->userCanApply($user, $gridView), 404);

        return response()->json([
            'view' => $this->gridViews->serialize($gridView, $user),
        ]);
    }

    public function store(StoreGridViewRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $view = $this->gridViews->create($user, $request->validated());

        return response()->json([
            'view' => $this->gridViews->serialize($view, $user),
        ], 201);
    }

    public function update(UpdateGridViewRequest $request, GridView $gridView): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $view = $this->gridViews->update($user, $gridView, $request->validated());

        return response()->json([
            'view' => $this->gridViews->serialize($view, $user),
        ]);
    }

    public function destroy(Request $request, GridView $gridView): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $this->gridViews->delete($user, $gridView);

        return response()->json(['ok' => true]);
    }
}
