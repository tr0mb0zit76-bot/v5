<?php

namespace App\Http\Requests;

use App\Support\RoleAccess;
use Illuminate\Foundation\Http\FormRequest;

class UpdateManagementExpenseCategoryRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'include_in_budget' => ['sometimes', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
