<?php

namespace App\Http\Requests;

use App\Support\RoleAccess;
use Illuminate\Foundation\Http\FormRequest;

class UpdateKpiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleAccess::canAccessSettingsMotivation($this->user());
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'bonus_multiplier' => ['required', 'numeric', 'min:0', 'max:100'],
            'deduction_rates' => ['required', 'array'],
            'deduction_rates.vat_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'deduction_rates.vat_zero_22_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'deduction_rates.cash_primary_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'deduction_rates.cash_secondary_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
