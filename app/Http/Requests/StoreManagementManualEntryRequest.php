<?php

namespace App\Http\Requests;

use App\Support\RoleAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManagementManualEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleAccess::canAccessPaymentReconcile($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bank_account_id' => ['required', 'integer', 'exists:management_bank_accounts,id'],
            'operation_date' => ['required', 'date'],
            'direction' => ['required', Rule::in(['in', 'out'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'description' => ['required', 'string', 'max:2000'],
            'allocation_type' => ['required', Rule::in(['operational', 'payroll', 'category'])],
            'category_id' => ['nullable', 'integer', 'exists:management_expense_categories,id'],
            'payment_schedule_id' => ['nullable', 'integer', 'exists:payment_schedules,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
