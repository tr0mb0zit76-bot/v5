<?php

namespace App\Http\Requests;

use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Аванс без привязки к зарплатному периоду ({@see SalaryPayout::$period_id} = null).
 */
class StoreSalaryUnscopedAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleAccess::canAccessFinanceSalary($this->user());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payout_date' => ['required', 'date'],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }
}
