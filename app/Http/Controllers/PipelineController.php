<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Pipeline\PipelineBoardService;
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PipelineController extends Controller
{
    public function __construct(
        private readonly PipelineBoardService $board,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $view = (string) $request->string('view', 'orders');

        if ($view === 'leads' && RoleAccess::canAccessVisibilityArea($user, 'leads')) {
            $slug = (string) $request->string('lead_process', 'transport-intake');

            return Inertia::render('Pipeline/Index', $this->board->buildLeadsBoard($user, $slug));
        }

        abort_unless(RoleAccess::canAccessVisibilityArea($user, 'orders'), 403);

        return Inertia::render('Pipeline/Index', $this->board->buildOrdersBoard($user));
    }

    public function markAccountingHandoff(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $this->board->markAccountingHandoff($order, $user);

        return redirect()
            ->route('pipeline.index', ['view' => 'orders'])
            ->with('success', 'Заказ отмечен как принятый бухгалтерией.');
    }
}
