<?php

namespace App\Services\Orders\Wizard;

use App\Models\Order;
use Illuminate\Support\Facades\Schema;

class OrderWizardIndexService
{
    public function loadForEditing(Order $order): Order
    {
        $relations = ['client', 'ownCompany', 'manager', 'legs.routePoints'];

        if (Schema::hasColumn('orders', 'order_owner_id')) {
            $relations[] = 'orderOwner';
        }

        if (Schema::hasColumn('orders', 'dispatcher_id')) {
            $relations[] = 'dispatcher';
        }

        if (Schema::hasTable('leg_contractor_assignments')) {
            $relations[] = 'legs.contractorAssignments';
            $relations[] = 'legs.contractorAssignment';
        }

        if (Schema::hasTable('leg_costs')) {
            $relations[] = 'legs.cost';
        }

        if (Schema::hasColumn('cargos', 'order_id')) {
            $relations[] = 'cargoItems';
        }

        if (Schema::hasTable('order_documents')) {
            $relations[] = 'documents';
        }

        if (Schema::hasTable('financial_terms')) {
            $relations[] = 'financialTerms';
        }

        if (Schema::hasTable('order_status_logs')) {
            $relations[] = 'statusLogs';
        }

        return $order->load($relations);
    }
}
