<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\Order;
use App\Models\PrintFormBasicTerm;
use App\Services\PrintForm\ContractorPrintFormChangeRequestService;
use App\Services\PrintForm\PrintFormBasicTermsService;
use App\Support\RoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderBasicTermsController extends Controller
{
    public function __construct(
        private readonly PrintFormBasicTermsService $basicTermsService,
        private readonly ContractorPrintFormChangeRequestService $printFormChanges,
    ) {}

    public function promoteToContractor(Request $request, Order $order): RedirectResponse
    {
        abort_unless($this->canPromoteBasicTerms($request), 403);

        $validated = $request->validate([
            'party' => ['required', 'string', Rule::in([
                PrintFormBasicTerm::PARTY_CUSTOMER,
                PrintFormBasicTerm::PARTY_CARRIER,
            ])],
            'manager_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $party = (string) $validated['party'];
        $payload = $this->basicTermsService->wizardPayloadForOrder($order, $party);
        $user = $request->user();
        abort_if($user === null, 403);

        $contractorId = $payload['contractor_id'] ?? null;

        if ($contractorId === null) {
            return redirect()
                ->back()
                ->with('error', 'В заказе нет контрагента для сохранения базовых условий.');
        }

        $contractor = $order->customer_id === $contractorId
            ? $order->customer
            : $order->carrier;

        if ($contractor === null) {
            $contractor = Contractor::query()->find($contractorId);
        }

        if ($contractor === null) {
            return redirect()
                ->back()
                ->with('error', 'Контрагент не найден.');
        }

        if ($this->printFormChanges->canDirectManagePrintForm($user)) {
            $this->basicTermsService->promoteOrderTermsToContractor($order, $party, $payload['items']);

            return redirect()
                ->back()
                ->with('success', $party === PrintFormBasicTerm::PARTY_CARRIER
                    ? 'Базовые условия перевозчика сохранены для контрагента.'
                    : 'Базовые условия заказчика сохранены для контрагента.');
        }

        $this->printFormChanges->submitBasicTermsChange(
            $contractor,
            $party,
            $payload['items'],
            $user,
            $validated['manager_notes'] ?? null,
        );

        return redirect()
            ->back()
            ->with('success', 'Условия отправлены на согласование руководителю. После утверждения они сохранятся в карточке контрагента.');
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

        return RoleAccess::canAccessVisibilityArea($user, 'contractors')
            || RoleAccess::canAccessSettingsSystem($user);
    }
}
