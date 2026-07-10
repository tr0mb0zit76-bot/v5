<?php

namespace App\Services\Orders\Wizard;

use App\Models\Order;
use App\Services\PrintForm\ContractorPrintFormChangeRequestService;
use App\Support\OrderPrintWorkflowLock;
use App\Support\OrderViewAuthorization;
use App\Support\RoleAccess;
use Illuminate\Http\Request;

class OrderWizardOrderAuthorization
{
    public function __construct(
        private readonly ContractorPrintFormChangeRequestService $contractorPrintFormChangeRequestService,
    ) {}

    public function canEditOrder(Request $request, Order $order): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        if ($user->isAdmin() || $user->isSupervisor()) {
            return true;
        }

        $field = $request->input('field');
        if (is_string($field) && RoleAccess::canClerkEditOrderInlineField($user, $field)) {
            return true;
        }

        if (! $user->isManager()) {
            return false;
        }

        if (! OrderViewAuthorization::userOwnsOrderRecord($order, (int) $user->id)) {
            return false;
        }

        return ! OrderPrintWorkflowLock::allPrintWorkflowDocumentsFinalized($order);
    }

    public function canPromoteBasicTerms(Request $request): bool
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

    public function canDirectPromoteBasicTerms(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        return $this->contractorPrintFormChangeRequestService->canDirectManagePrintForm($user);
    }
}
