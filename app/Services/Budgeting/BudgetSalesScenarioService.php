<?php

namespace App\Services\Budgeting;

use App\Models\BudgetScenario;

class BudgetSalesScenarioService
{
    public function ensureForCompanyScenario(BudgetScenario $companyScenario): BudgetScenario
    {
        $existing = BudgetScenario::query()
            ->where('parent_scenario_id', $companyScenario->id)
            ->where('plan_type', BudgetScenario::PLAN_TYPE_SALES_PAYROLL)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return BudgetScenario::query()->create([
            'name' => 'План продавцов',
            'plan_type' => BudgetScenario::PLAN_TYPE_SALES_PAYROLL,
            'parent_scenario_id' => $companyScenario->id,
            'inputs' => [],
        ]);
    }
}
