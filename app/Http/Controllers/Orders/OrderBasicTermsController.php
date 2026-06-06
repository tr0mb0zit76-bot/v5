<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PrintFormBasicTerm;
use App\Services\PrintForm\PrintFormBasicTermsService;
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderBasicTermsController extends Controller
{
    public function __construct(
        private readonly PrintFormBasicTermsService $basicTermsService,
    ) {}

    public function promoteToContractor(Request $request, Order $order): RedirectResponse
    {
        abort_unless($this->canPromoteBasicTerms($request), 403);

        $validated = $request->validate([
            'party' => ['required', 'string', Rule::in([
                PrintFormBasicTerm::PARTY_CUSTOMER,
                PrintFormBasicTerm::PARTY_CARRIER,
            ])],
        ]);

        $party = (string) $validated['party'];
        $payload = $this->basicTermsService->wizardPayloadForOrder($order, $party);

        $this->basicTermsService->promoteOrderTermsToContractor($order, $party, $payload['items']);

        return redirect()
            ->back()
            ->with('success', $party === PrintFormBasicTerm::PARTY_CARRIER
                ? 'Базовые условия перевозчика сохранены для контрагента.'
                : 'Базовые условия заказчика сохранены для контрагента.');
    }

    private function canPromoteBasicTerms(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if ($user->isAdmin() || $user->isSupervisor()) {
            return true;
        }

        return RoleAccess::canAccessSettingsSystem($user);
    }
}
