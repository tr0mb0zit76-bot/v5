<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderCarrierPortalDocumentRequest;
use App\Http\Requests\SubmitOrderCarrierPortalRequest;
use App\Models\Contractor;
use App\Models\Order;
use App\Models\OrderLeg;
use App\Models\OrderPortalInvite;
use App\Models\RoutePoint;
use App\Services\OrderCarrierPortalDocumentService;
use App\Services\OrderCarrierPortalSubmissionService;
use App\Services\OrderPortalInviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class OrderCarrierPortalController extends Controller
{
    public function __construct(
        private readonly OrderPortalInviteService $inviteService,
        private readonly OrderCarrierPortalSubmissionService $submissionService,
        private readonly OrderCarrierPortalDocumentService $portalDocumentService,
    ) {}

    public function show(Request $request, string $token): Response
    {
        $invite = $this->resolveInviteOrAbort($token);

        if ($invite->isOpenForSubmission()) {
            $invite->forceFill(['last_opened_at' => now()])->save();
        }

        return Inertia::render('Portal/CarrierFleet', array_merge(
            $this->portalPayload($invite),
            ['portal_token' => $token],
        ));
    }

    public function store(SubmitOrderCarrierPortalRequest $request, string $token): RedirectResponse
    {
        $invite = $this->resolveInviteOrAbort($token);

        abort_unless($invite->isOpenForSubmission(), 410);

        $this->submissionService->submit($invite, $request->validated());

        return redirect()
            ->route('portal.carrier.show', ['token' => $token])
            ->with('flash', [
                'type' => 'success',
                'message' => 'Данные отправлены. Менеджер увидит их в заказе.',
            ]);
    }

    public function storeDocument(StoreOrderCarrierPortalDocumentRequest $request, string $token): RedirectResponse|JsonResponse
    {
        $invite = $this->resolveInviteOrAbort($token);

        abort_unless($invite->isOpenForSubmission(), 410);

        $file = $request->file('file');
        abort_if($file === null, 422);

        $this->portalDocumentService->store($invite, $request->validated(), $file);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'document_slots' => $this->portalDocumentService->documentSlotsForInvite(
                    $invite->refresh()->load(['order.documents']),
                ),
            ]);
        }

        return redirect()
            ->route('portal.carrier.show', ['token' => $token])
            ->with('flash', [
                'type' => 'success',
                'message' => 'Документ загружен.',
            ]);
    }

    private function resolveInviteOrAbort(string $token): OrderPortalInvite
    {
        $invite = $this->inviteService->resolveByToken($token);

        abort_if($invite === null, 404, 'Ссылка не найдена.');
        abort_if($invite->isRevoked(), 410, 'Ссылка отозвана.');
        abort_if($invite->isExpired(), 410, 'Срок действия ссылки истёк.');

        return $invite->loadMissing(['order.documents', 'contractor']);
    }

    /**
     * @return array<string, mixed>
     */
    private function portalPayload(OrderPortalInvite $invite): array
    {
        /** @var Order $order */
        $order = $invite->order;
        /** @var Contractor $contractor */
        $contractor = $invite->contractor;

        $submission = is_array($invite->submitted_payload) ? $invite->submitted_payload : null;

        return [
            'status' => $invite->isSubmitted() ? 'submitted' : ($invite->isOpenForSubmission() ? 'open' : 'closed'),
            'expires_at' => $invite->expires_at?->toIso8601String(),
            'submitted_at' => $invite->used_at?->toIso8601String(),
            'submission' => $submission,
            'order' => [
                'order_number' => $order->order_number,
                'loading_date' => optional($order->loading_date)?->toDateString(),
                'unloading_date' => optional($order->unloading_date)?->toDateString(),
            ],
            'carrier' => [
                'name' => $contractor->name,
                'inn' => $contractor->inn,
            ],
            'leg' => [
                'stage' => $invite->stage,
                'label' => $this->legLabel($invite->stage),
                'carrier_slot' => $invite->carrier_slot,
            ],
            'route_summary' => $this->routeSummaryForLeg($order, $invite->stage),
            'form_defaults' => $submission ?? [],
            'document_slots' => $this->portalDocumentService->documentSlotsForInvite($invite),
        ];
    }

    private function legLabel(string $stage): string
    {
        if (preg_match('/^leg_(\d+)$/', $stage, $matches) === 1) {
            return 'Плечо '.$matches[1];
        }

        return $stage;
    }

    /**
     * @return list<array{title: string, address: string|null, planned_date: string|null}>
     */
    private function routeSummaryForLeg(Order $order, string $stage): array
    {
        if (! Schema::hasTable('order_legs') || ! Schema::hasTable('route_points')) {
            return [];
        }

        $leg = OrderLeg::query()
            ->where('order_id', $order->id)
            ->where('description', $stage)
            ->first();

        if ($leg === null) {
            return [];
        }

        return RoutePoint::query()
            ->where('order_leg_id', $leg->id)
            ->orderBy('sequence')
            ->get()
            ->map(function (RoutePoint $point): array {
                $type = (string) $point->type;
                $title = match ($type) {
                    'loading' => 'Погрузка',
                    'unloading' => 'Выгрузка',
                    'border_crossing' => 'Граница',
                    default => 'Точка маршрута',
                };

                return [
                    'title' => $title,
                    'address' => $point->address ?: data_get($point->normalized_data, 'address'),
                    'planned_date' => optional($point->planned_date)?->toDateString(),
                ];
            })
            ->values()
            ->all();
    }
}
