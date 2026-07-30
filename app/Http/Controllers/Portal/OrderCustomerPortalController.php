<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderCustomerPortalDocumentRequest;
use App\Models\Contractor;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\OrderPortalInvite;
use App\Services\OrderCustomerPortalDocumentService;
use App\Services\OrderCustomerPortalPresentationService;
use App\Services\OrderPortalInviteAccessService;
use App\Services\OrderPortalInviteService;
use App\Services\OrderPortalOutgoingDocumentService;
use App\Support\DocumentUploadLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderCustomerPortalController extends Controller
{
    public function __construct(
        private readonly OrderPortalInviteService $inviteService,
        private readonly OrderPortalInviteAccessService $inviteAccessService,
        private readonly OrderCustomerPortalDocumentService $portalDocumentService,
        private readonly OrderPortalOutgoingDocumentService $outgoingDocumentService,
        private readonly OrderCustomerPortalPresentationService $presentationService,
    ) {}

    public function show(Request $request, string $token): Response
    {
        $invite = $this->resolveInviteOrAbort($token, allowClosed: true);

        if ($this->inviteAccessService->canUploadDocuments($invite->order, $invite)) {
            $invite->forceFill(['last_opened_at' => now()])->save();
        }

        return Inertia::render('Portal/CustomerDocuments', array_merge(
            $this->portalPayload($invite, $token),
            ['portal_token' => $token],
        ));
    }

    public function storeDocument(StoreOrderCustomerPortalDocumentRequest $request, string $token): RedirectResponse|JsonResponse
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
            ->route('portal.customer.show', ['token' => $token])
            ->with('flash', [
                'type' => 'success',
                'message' => 'Документ загружен.',
            ]);
    }

    public function downloadOutgoing(string $token, OrderDocument $orderDocument): StreamedResponse
    {
        $invite = $this->resolveInviteOrAbort($token, allowClosed: true);

        return $this->outgoingDocumentService->downloadForInvite($invite, $orderDocument, 'customer');
    }

    private function resolveInviteOrAbort(string $token, bool $allowClosed = false): OrderPortalInvite
    {
        $invite = $this->inviteService->resolveCustomerByToken($token);

        abort_if($invite === null, 404, 'Ссылка не найдена.');
        abort_if($invite->isRevoked(), 410, 'Ссылка отозвана.');
        abort_if($invite->isExpired(), 410, 'Ссылка истекла.');

        $invite->loadMissing(['order.documents', 'order.legs.routePoints', 'contractor']);

        if (! $allowClosed && $this->inviteAccessService->isInviteClosed($invite->order, $invite)) {
            abort(410, 'Ссылка закрыта: перевозка завершена.');
        }

        return $invite;
    }

    /**
     * @return array<string, mixed>
     */
    private function portalPayload(OrderPortalInvite $invite, string $token): array
    {
        /** @var Order $order */
        $order = $invite->order;
        /** @var Contractor $customer */
        $customer = $invite->contractor;

        $canUploadDocuments = $this->inviteAccessService->canUploadDocuments($order, $invite);
        $unloadingActual = $this->inviteAccessService->unloadingActualForInvite($order, $invite);
        $tripStatus = $this->presentationService->tripStatus($order);
        $routeMilestones = $this->presentationService->routeMilestones($order);

        return [
            'status' => $canUploadDocuments ? 'open' : 'closed',
            'link_validity_hint' => $this->inviteAccessService->linkValidityHint(),
            'unloading_actual' => $unloadingActual,
            'can_upload_documents' => $canUploadDocuments,
            'trip_status' => $tripStatus,
            'route_milestones' => $routeMilestones,
            'order' => [
                'order_number' => $order->order_number,
                'status' => $tripStatus['code'],
                'status_label' => $tripStatus['label'],
                'loading_date' => optional($order->loading_date)?->toDateString(),
                'unloading_date' => optional($order->unloading_date)?->toDateString(),
            ],
            'customer' => [
                'name' => $customer->name,
                'inn' => $customer->inn,
            ],
            'document_slots' => $this->portalDocumentService->documentSlotsForInvite($invite),
            'outgoing_documents' => $this->outgoingDocumentService->listForInvite(
                $invite,
                'customer',
                'portal.customer.documents.download',
                $token,
            ),
            'document_upload_limits' => DocumentUploadLimits::forSharedInertia(),
            'traklo_apk_url' => config('external_users.apk_url', '/downloads/traklo.apk'),
        ];
    }
}
