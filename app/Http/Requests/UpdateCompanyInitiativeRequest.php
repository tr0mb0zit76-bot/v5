<?php

namespace App\Http\Requests;

use App\Support\CompanyPlanningCatalog;
use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyInitiativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleAccess::canAccessCompanyPlanning($this->user());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'goal' => ['nullable', 'string', 'max:5000'],
            'expected_result' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', Rule::in(CompanyPlanningCatalog::INITIATIVE_STATUSES)],
            'priority' => ['nullable', 'string', Rule::in(CompanyPlanningCatalog::PRIORITIES)],
            'direction' => ['nullable', 'string', Rule::in(CompanyPlanningCatalog::DIRECTIONS)],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'planned_budget_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'budget_currency' => ['nullable', 'string', 'size:3'],
            'management_expense_category_id' => ['nullable', 'integer'],
            'budget_notes' => ['nullable', 'string', 'max:5000'],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'risk_level' => ['nullable', 'string', Rule::in(CompanyPlanningCatalog::RISK_LEVELS)],
            'risk_summary' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Укажите название инициативы.',
            'ends_on.after_or_equal' => 'Дедлайн не может быть раньше даты старта.',
        ];
    }
}
