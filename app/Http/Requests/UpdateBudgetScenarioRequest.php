<?php

namespace App\Http\Requests;

use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'inputs.horizon_months' => ['nullable', 'integer', 'min:6', 'max:36'],
            'inputs.breakeven_month' => ['nullable', 'integer', 'min:1', 'max:36'],
            'inputs.target_dividends_month' => ['nullable', 'integer', 'min:1', 'max:36'],
            'inputs.target_dividends_amount' => ['nullable', 'numeric', 'min:0'],
            'inputs.owner_investment' => ['nullable', 'numeric', 'min:0'],
            'inputs.office_monthly' => ['nullable', 'numeric', 'min:0'],
            'inputs.accounting_monthly' => ['nullable', 'numeric', 'min:0'],
            'inputs.manager_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'inputs.manager_payroll_monthly' => ['nullable', 'numeric', 'min:0'],
            'inputs.manager_payroll_months' => ['nullable', 'integer', 'min:0', 'max:36'],
        ];
    }
}
