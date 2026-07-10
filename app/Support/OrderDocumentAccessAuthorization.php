<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;
use App\Models\User;

/**
 * Доступ к документам заказа: реестр, превью, вкладка «Документы» в мастере.
 */
final class OrderDocumentAccessAuthorization
{
    public static function userMayViewDocuments(?User $user, Order $order): bool
    {
        if ($user === null) {
            return false;
        }

        if (self::userMayManageDocuments($user, $order)) {
            return true;
        }

        if ($user->hasSigningAuthority()) {
            $order->loadMissing('documents');

            return OrderPrintWorkflowLock::orderHasPrintDocumentPendingApproval($order)
                && $user->canSignDocumentsForOwnCompany($order->own_company_id);
        }

        return false;
    }

    public static function userMayManageDocuments(?User $user, Order $order): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isAdmin() || $user->isSupervisor()) {
            return true;
        }

        if (RoleAccess::resolveVisibilityScopeForUser($user, 'documents') === 'all') {
            return true;
        }

        return $user->isManager()
            && OrderViewAuthorization::userCanViewOrderForArea($user, $order, 'documents');
    }
}
