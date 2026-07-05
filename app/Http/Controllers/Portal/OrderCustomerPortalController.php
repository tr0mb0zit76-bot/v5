<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderCustomerPortalDocumentRequest;
use App\Models\Contractor;
use App\Models\Order;
use App\Models\OrderPortalInvite;
use App\Services\OrderCustomerPortalDocumentService;
use App\Services\OrderPortalInviteAccessService;
use App\Services\OrderPortalInviteService;
use App\Support\DocumentUploadLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderCustomerPortalController extends Controller
{
    public function __construct(
        private readonly OrderPortalInviteService $inviteService,
        private readonly OrderPortalInviteAccessService $inviteAccessService,
        private readonly OrderCustomerPortalDocumentService $portalDocumentService,
    ) {}

    public function show(Request $request, string $token): Response
    {
        $invite = $this->resolveInviteOrAbort($token, allowClosed: true);

        if ($this->inviteAccessService->canUploadDocuments($invite->order, $invite)) {
            $invite->forceFill(['last_opened_at' => now()])->save();
        }

        return Inertia::render('Portal/CustomerDocuments', array_merge(
            $this->portalPayload($invite),
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

    private function resolveInviteOrAbort(string $token, bool $allowClosed = false): OrderPortalInvite
    {
        $invite = $this->inviteService->resolveCustomerByToken($token);

        abort_if($invite === null, 404, 'Ссылка не найдена.');
        abort_if($invite->isRevoked(), 410, 'Ссылка отозвана.');

        $invite->loadMissing(['order.documents', 'contractor']);

        if (! $allowClosed && $this->inviteAccessService->isInviteClosed($invite->order, $invite)) {
            abort(410, 'Ссылка закрыта: перевозка завершена.');
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
        /** @var Contractor $customer */
        $customer = $invite->contractor;

        $canUploadDocuments = $this->inviteAccessService->canUploadDocuments($order, $invite);
        $unloadingActual = $this->inviteAccessService->unloadingActualForInvite($order, $invite);

        return [
            'status' => $canUploadDocuments ? 'open' : 'closed',
            'link_validity_hint' => $this->inviteAccessService->linkValidityHint(),
            'unloading_actual' => $unloadingActual,
            'can_upload_documents' => $canUploadDocuments,
            'order' => [
                'order_number' => $order->order_number,
                'loading_date' => optional($order->loading_date)?->toDateString(),
                'unloading_date' => optional($order->unloading_date)?->toDateString(),
            ],
            'customer' => [
                'name' => $customer->name,
                'inn' => $customer->inn,
            ],
            'document_slots' => $this->portalDocumentService->documentSlotsForInvite($invite),
            'document_upload_limits' => DocumentUploadLimits::forSharedInertia(),
            'traklo_apk_url' => config('external_users.apk_url', '/downloads/traklo.apk'),
        ];
    }
}
