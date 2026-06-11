<?php

namespace App\Http\Requests;

use App\Support\RoleAccess;
use Illuminate\Foundation\Http\FormRequest;

class StoreManagementExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleAccess::canAccessManagementAccounting($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:management_expense_categories,id'],
            'flow' => ['nullable', 'string', 'in:in,out'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название статьи.',
        ];
    }
}
