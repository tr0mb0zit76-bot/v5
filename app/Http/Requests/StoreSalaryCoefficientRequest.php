<?php

namespace App\Http\Requests;

use App\Support\RoleAccess;
use Illuminate\Foundation\Http\FormRequest;

class StoreSalaryCoefficientRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->routeIs('finance.salary.*')) {
            return RoleAccess::canAccessFinanceSalary($this->user());
        }

        return RoleAccess::canAccessSettingsMotivation($this->user());
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'manager_id' => ['required', 'integer', 'exists:users,id'],
            'base_salary' => ['required', 'integer', 'min:0'],
            'bonus_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
