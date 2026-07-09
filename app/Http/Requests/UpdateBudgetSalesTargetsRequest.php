<?php

namespace App\Http\Requests;

use App\Models\BudgetSalesTarget;
use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBudgetSalesTargetsRequest extends FormRequest
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
            'period_month' => ['required', 'date'],
            'targets' => ['required', 'array'],
            'targets.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'targets.*.metric' => ['required', 'string', Rule::in(BudgetSalesTarget::metrics())],
            'targets.*.planned_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period_month.required' => 'Укажите месяц плана.',
            'targets.required' => 'Нет данных для сохранения.',
            'targets.*.metric.in' => 'Недопустимый показатель плана.',
        ];
    }
}
