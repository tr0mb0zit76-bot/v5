<?php

namespace App\Http\Requests;

use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalaryPayoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if ($this->routeIs('finance.salary.*')) {
            return RoleAccess::canAccessFinanceSalary($this->user());
        }

        return RoleAccess::canAccessSettingsMotivation($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payout_date' => ['required', 'date'],
            'type' => ['nullable', 'string', Rule::in(['salary', 'advance', 'correction'])],
            'comment' => ['nullable', 'string'],
        ];
    }
}
