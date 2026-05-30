<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderCarrierPortalDocumentRequest;
use App\Http\Requests\StoreOrderCarrierPortalFleetDocumentRequest;
use App\Http\Requests\SubmitOrderCarrierPortalRequest;
use App\Models\Contractor;
use App\Models\Order;
use App\Models\OrderLeg;
use App\Models\OrderPortalInvite;
use App\Models\RoutePoint;
use App\Services\OrderCarrierPortalDocumentService;
use App\Services\OrderCarrierPortalFleetDocumentService;
use App\Services\OrderCarrierPortalSubmissionService;
use App\Services\OrderPortalInviteAccessService;
use App\Services\OrderPortalInviteService;
use App\Support\DocumentUploadLimits;
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
        private readonly OrderPortalInviteAccessService $inviteAccessService,
        private readonly OrderCarrierPortalSubmissionService $submissionService,
        private readonly OrderCarrierPortalDocumentService $portalDocumentService,
        private readonly OrderCarrierPortalFleetDocumentService $fleetDocumentService,
    ) {}

    public function show(Request $request, string $token): Response
    {
        $invite = $this->resolveInviteOrAbort($token, allowClosed: true);

        if ($this->inviteAccessService->canUploadDocuments($invite->order, $invite)) {
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
        abort_unless($this->inviteAccessService->canSubmitFleetForm($invite->order, $invite), 410);

        $this->submissionService->submit($invite, $request->validated());

        return redirect()
            ->route('portal.carrier.show', ['token' => $token])
            ->with('flash', [
                'type' => 'success',
                'message' => 'Данные отправлены. Вы можете продолжить загружать закрывающие документы до завершения перевозки.',
            ]);
    }

    public function storeDocument(StoreOrderCarrierPortalDocumentRequest $request, string $token): RedirectResponse|JsonResponse
    {
        $invite = $this->resolveInviteOrAbort($token);
        abort_unless($this->inviteAccessService->canUploadDocuments($invite->order, $invite), 410);

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

    public function storeFleetDocument(StoreOrderCarrierPortalFleetDocumentRequest $request, string $token): JsonResponse
    {
        $invite = $this->resolveInviteOrAbort($token);
        abort_unless($this->inviteAccessService->canUploadDocuments($invite->order, $invite), 410);

        $file = $request->file('file');
        abort_if($file === null, 422);

        $this->fleetDocumentService->store($invite, $request->validated(), $file);

        return response()->json([
            'ok' => true,
            'fleet_document_sections' => $this->fleetDocumentService->fleetDocumentSections(
                $invite,
                $request->validated(),
            ),
        ]);
    }

    private function resolveInviteOrAbort(string $token, bool $allowClosed = false): OrderPortalInvite
    {
        $invite = $this->inviteService->resolveByToken($token);

        abort_if($invite === null, 404, 'Ссылка не найдена.');
        abort_if($invite->isRevoked(), 410, 'Ссылка отозвана.');

        $invite->loadMissing(['order.documents', 'order.legs.routePoints', 'contractor']);

        if (! $allowClosed && $this->inviteAccessService->isInviteClosed($invite->order, $invite)) {
            abort(410, 'Ссылка закрыта: проставлена фактическая дата выгрузки.');
        }

        return $invite;
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
        $formDefaults = $submission ?? [];
        $canUploadDocuments = $this->inviteAccessService->canUploadDocuments($order, $invite);
        $canSubmitFleetForm = $this->inviteAccessService->canSubmitFleetForm($order, $invite);
        $unloadingActual = $this->inviteAccessService->unloadingActualForInvite($order, $invite);

        $status = 'closed';
        if ($canUploadDocuments) {
            $status = $invite->isSubmitted() ? 'submitted' : 'open';
        }

        return [
            'status' => $status,
            'link_validity_hint' => $this->inviteAccessService->linkValidityHint(),
            'unloading_actual' => $unloadingActual,
            'can_upload_documents' => $canUploadDocuments,
            'can_submit_fleet_form' => $canSubmitFleetForm,
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
            'form_defaults' => $formDefaults,
            'document_slots' => $this->portalDocumentService->documentSlotsForInvite($invite),
            'fleet_document_sections' => $this->fleetDocumentService->fleetDocumentSections($invite, $formDefaults),
            'document_upload_limits' => DocumentUploadLimits::forSharedInertia(),
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
