<?php

namespace App\Http\Requests;

use App\Support\CompanyPlanningCatalog;
use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyInitiativeMilestoneRequest extends FormRequest
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
            'done_criteria' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', Rule::in(CompanyPlanningCatalog::MILESTONE_STATUSES)],
            'priority' => ['nullable', 'string', Rule::in(CompanyPlanningCatalog::PRIORITIES)],
            'responsible_id' => ['nullable', 'integer', 'exists:users,id'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'completed_on' => ['nullable', 'date'],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Укажите название этапа.',
            'ends_on.after_or_equal' => 'Конец этапа не может быть раньше начала.',
        ];
    }
}
