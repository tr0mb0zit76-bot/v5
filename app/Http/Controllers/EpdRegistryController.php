<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Epd\LinkEpdRegistryEntryRequest;
use App\Models\OneCEpdRegistryEntry;
use App\Models\Order;
use App\Services\OneC\OneCEpdRegistryLinkService;
use App\Services\OneC\OneCEpdRegistrySyncService;
use App\Support\OrderViewAuthorization;
use App\Support\RoleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class EpdRegistryController extends Controller
{
    public function __construct(
        private readonly OneCEpdRegistrySyncService $sync,
        private readonly OneCEpdRegistryLinkService $linkService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $type = trim((string) $request->query('type', ''));
        $unlinkedOnly = $request->boolean('unlinked');
        $q = trim((string) $request->query('q', ''));

        $query = OneCEpdRegistryEntry::query()
            ->with(['order:id,order_number'])
            ->where('deletion_mark', false)
            ->orderByDesc('epd_date')
            ->orderByDesc('id');

        if ($type !== '' && $type !== 'all') {
            $query->where('document_type', $type);
        }

        if ($unlinkedOnly) {
            $query->whereNull('order_id');
        }

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($builder) use ($like): void {
                $builder->where('epd_number', 'like', $like)
                    ->orWhere('ib_number', 'like', $like)
                    ->orWhere('shipper_name', 'like', $like)
                    ->orWhere('shipper_inn', 'like', $like)
                    ->orWhere('carrier_name', 'like', $like)
                    ->orWhere('document_ref', 'like', $like);
            });
        }

        $rows = $query->limit(300)->get()->map(fn (OneCEpdRegistryEntry $row): array => $row->toGridRow())->values()->all();

        return Inertia::render('Epd/Index', [
            'rows' => $rows,
            'filters' => [
                'type' => $type !== '' ? $type : 'all',
                'unlinked' => $unlinkedOnly,
                'q' => $q,
            ],
            'typeOptions' => [
                ['value' => 'all', 'label' => 'Все типы'],
                ['value' => OneCEpdRegistryEntry::TYPE_EXPEDITION_ORDER, 'label' => 'Поручение экспедитору'],
                ['value' => OneCEpdRegistryEntry::TYPE_EXPEDITION_RECEIPT, 'label' => 'Экспедиторская расписка'],
                ['value' => OneCEpdRegistryEntry::TYPE_ETRN, 'label' => 'ЭТрН'],
                ['value' => OneCEpdRegistryEntry::TYPE_E_ORDER, 'label' => 'Электронный заказ (заявка)'],
                ['value' => OneCEpdRegistryEntry::TYPE_E_WORK_ORDER, 'label' => 'Электронный заказ-наряд'],
                ['value' => OneCEpdRegistryEntry::TYPE_UNKNOWN, 'label' => 'Прочее / неизвестный'],
            ],
            'canSync' => (bool) config('one_c.enabled'),
            'lastSyncedAt' => OneCEpdRegistryEntry::query()->max('last_synced_at'),
        ]);
    }

    public function syncNow(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $stats = $this->sync->sync();

        return response()->json([
            'ok' => ($stats['errors'] ?? 0) === 0,
            'stats' => $stats,
        ]);
    }

    public function searchOrders(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $q = trim((string) $request->query('q', ''));
        if ($q === '' || mb_strlen($q) < 1) {
            return response()->json(['orders' => []]);
        }

        $user = $request->user();
        abort_unless($user !== null, 401);

        $builder = Order::query()
            ->select(['id', 'order_number', 'order_date', 'customer_id', 'manager_id'])
            ->with(['client:id,name'])
            ->orderByDesc('id')
            ->limit(40);

        if (Schema::hasColumn('orders', 'order_number')) {
            $builder->where(function ($query) use ($q): void {
                $query->where('order_number', 'like', '%'.$q.'%');
                if (ctype_digit($q)) {
                    $query->orWhere('id', (int) $q);
                }
            });
        }

        $orders = $builder->get()
            ->filter(static fn (Order $order): bool => OrderViewAuthorization::userCanViewOrder($user, $order))
            ->take(20)
            ->map(static fn (Order $order): array => [
                'id' => (int) $order->id,
                'order_number' => (string) ($order->order_number ?? ''),
                'order_date' => optional($order->order_date)?->toDateString(),
                'customer_name' => $order->client?->name,
            ])
            ->values()
            ->all();

        return response()->json(['orders' => $orders]);
    }

    public function link(LinkEpdRegistryEntryRequest $request, OneCEpdRegistryEntry $entry): JsonResponse
    {
        $order = Order::query()->findOrFail((int) $request->validated('order_id'));
        $linked = $this->linkService->link($entry, $order, $request->user());

        return response()->json([
            'ok' => true,
            'entry' => $linked->toGridRow(),
        ]);
    }

    public function unlink(Request $request, OneCEpdRegistryEntry $entry): JsonResponse
    {
        $this->authorizeAccess($request);
        $unlinked = $this->linkService->unlink($entry, $request->user());

        return response()->json([
            'ok' => true,
            'entry' => $unlinked->toGridRow(),
        ]);
    }

    private function authorizeAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        if ($user->isAdmin()) {
            return;
        }

        abort_unless(
            RoleAccess::canAccessVisibilityArea($user, 'documents')
            || RoleAccess::canAccessVisibilityArea($user, 'orders'),
            403
        );
    }
}
