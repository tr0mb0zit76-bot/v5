<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\External\StoreCounterpartyOrderDocumentRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\ExternalUsers\CounterpartyOrderDocumentService;
use App\Support\CounterpartyOrderAccess;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class MobileCounterpartyShellController extends Controller
{
    public function __construct(
        private readonly CounterpartyOrderAccess $counterpartyOrderAccess,
        private readonly CounterpartyOrderDocumentService $counterpartyOrderDocumentService,
    ) {}

    public function orders(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isExternal(), 403);

        abort_unless(
            RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'counterparty_orders'),
            403,
        );

        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $needle = trim((string) ($validated['q'] ?? ''));

        $query = $this->counterpartyOrderAccess
            ->ordersQueryForUser($user)
            ->with(['client:id,name', 'carrier:id,name']);

        if ($needle !== '') {
            $like = '%'.$needle.'%';
            $query->where(function ($builder) use ($like, $needle): void {
                $builder->where('order_number', 'like', $like);

                if (Schema::hasColumn('orders', 'order_customer_number')) {
                    $builder->orWhere('order_customer_number', 'like', $like);
                }

                if (preg_match('/^\d+$/', $needle) === 1) {
                    $builder->orWhere('orders.id', (int) $needle);
                }
            });
        }

        $orders = $query
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(fn (Order $order): array => $this->serializeOrder($order, $user))
            ->values();

        return response()->json(['orders' => $orders]);
    }

    public function orderSummary(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isExternal(), 403);
        abort_unless($this->counterpartyOrderAccess->userCanViewOrder($user, $order), 403);

        return response()->json([
            'order' => $this->serializeOrder($order->load(['client:id,name', 'carrier:id,name']), $user),
        ]);
    }

    public function orderDocumentSlots(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isExternal(), 403);

        return response()->json(
            $this->counterpartyOrderDocumentService->documentSlotsForUser($user, $order),
        );
    }

    public function storeDocument(StoreCounterpartyOrderDocumentRequest $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isExternal(), 403);

        $document = $this->counterpartyOrderDocumentService->store(
            $user,
            $order,
            $request->validated(),
            $request->file('file'),
        );

        return response()->json([
            'document' => [
                'id' => $document->id,
                'type' => $document->type,
                'original_name' => $document->original_name,
            ],
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrder(Order $order, User $user): array
    {
        $payload = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'updated_at' => $order->updated_at?->toIso8601String(),
            'customer_name' => $order->client?->name,
        ];

        if ($user->externalParty()?->value === 'carrier') {
            $payload['route_summary'] = $this->routeSummary($order);
        } else {
            $payload['route_summary'] = $this->routeSummary($order);
            $payload['carrier_name'] = null;
        }

        return $payload;
    }

    private function routeSummary(Order $order): ?string
    {
        $loading = trim((string) ($order->loading_city ?? $order->loading_address ?? ''));
        $unloading = trim((string) ($order->unloading_city ?? $order->unloading_address ?? ''));

        if ($loading === '' && $unloading === '') {
            return null;
        }

        if ($loading === '') {
            return $unloading;
        }

        if ($unloading === '') {
            return $loading;
        }

        return $loading.' → '.$unloading;
    }
}
