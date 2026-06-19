<?php

namespace App\Http\Requests;

use App\Support\RoleAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AllocateManagementStatementLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleAccess::canManageStatementImport($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'allocation_type' => ['required', Rule::in(['operational', 'payroll', 'category'])],
            'category_id' => ['nullable', 'integer', 'exists:management_expense_categories,id'],
            'payment_schedule_id' => ['nullable', 'integer', 'exists:payment_schedules,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:500'],
            'allocations' => ['nullable', 'array', 'min:2'],
            'allocations.*.payment_schedule_id' => ['required_with:allocations', 'integer', 'exists:payment_schedules,id'],
            'allocations.*.amount' => ['required_with:allocations', 'numeric', 'min:0.01'],
        ];
    }
}
