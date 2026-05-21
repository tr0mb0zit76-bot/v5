<?php

namespace App\Http\Requests;

use App\Services\Budgeting\BudgetPlannerService;
use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBudgetScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleAccess::canAccessBudgeting($this->user());
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'inputs' => ['required', 'array'],
            'inputs.calculation_mode' => ['nullable', 'string', Rule::in([BudgetPlannerService::MODE_TOP_DOWN, BudgetPlannerService::MODE_BOTTOM_UP])],
            'inputs.horizon_months' => ['nullable', 'integer', 'min:6', 'max:36'],
            'inputs.breakeven_month' => ['nullable', 'integer', 'min:1', 'max:36'],
            'inputs.cash_zero_month' => ['nullable', 'integer', 'min:1', 'max:36'],
            'inputs.target_dividends_month' => ['nullable', 'integer', 'min:1', 'max:36'],
            'inputs.target_dividends_amount' => ['nullable', 'numeric', 'min:0'],
            'inputs.owner_investment' => ['nullable', 'numeric', 'min:0'],
            'inputs.manager_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'inputs.margin_per_manager' => ['nullable', 'numeric', 'min:0'],
            'inputs.use_db_margin_per_manager' => ['nullable', 'boolean'],
        ];
    }
}
