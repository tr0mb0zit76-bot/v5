<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderClaimRequest;
use App\Http\Requests\UpdateOrderClaimRequest;
use App\Models\Order;
use App\Models\OrderClaim;
use App\Services\OrderClaimService;
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderClaimController extends Controller
{
    public function __construct(
        private readonly OrderClaimService $claims,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless(RoleAccess::canAccessVisibilityArea($user, 'claims'), 403);
        abort_unless($this->claims->featureEnabled($user), 404);

        $filters = [
            'status' => $request->string('status')->toString() ?: null,
            'q' => $request->string('q')->toString() ?: null,
        ];

        return Inertia::render('Claims/Index', [
            'claims' => $this->claims->listForUser($user, array_filter($filters))->values()->all(),
            'filters' => $filters,
            'statusOptions' => $this->claims->statusOptions(),
            'typeOptions' => $this->claims->typeOptions(),
            'partyOptions' => $this->claims->partyOptions(),
        ]);
    }

    public function store(StoreOrderClaimRequest $request, Order $order): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $claim = $this->claims->create($order, $user, $request->validated());

        return redirect()
            ->route('orders.edit', ['order' => $order->id, 'tab' => 'claims'])
            ->with('success', 'Претензия '.$claim->number.' создана.');
    }

    public function update(UpdateOrderClaimRequest $request, Order $order, OrderClaim $claim): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless((int) $claim->order_id === (int) $order->id, 404);

        $this->claims->update($claim, $user, $request->validated());

        return redirect()
            ->route('orders.edit', ['order' => $order->id, 'tab' => 'claims'])
            ->with('success', 'Претензия обновлена.');
    }
}
