<?php

namespace App\Http\Requests;

use App\Support\KpiDeductionCarrierRule;
use App\Support\PaymentFormDictionary;
use App\Support\RoleAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKpiDeductionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleAccess::canAccessSettingsMotivation($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->sharedRules();
    }

    /**
     * @return array<string, mixed>
     */
    protected function sharedRules(): array
    {
        $allowedForms = PaymentFormDictionary::allowedCodesForValidation();

        return [
            'name' => ['required', 'string', 'max:120'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'customer_payment_form' => ['nullable', 'string', Rule::in($allowedForms)],
            'customer_positive_vat_required' => ['sometimes', 'boolean'],
            'customer_vat_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'carrier_rule' => ['required', 'string', Rule::in(KpiDeductionCarrierRule::values())],
            'carrier_payment_forms' => ['nullable', 'array'],
            'carrier_payment_forms.*' => ['string', Rule::in($allowedForms)],
            'carrier_vat_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'deduction_primary_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'deduction_secondary_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'margin_supplement_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'margin_supplement_carrier_vat_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название условия.',
            'priority.required' => 'Укажите приоритет.',
            'carrier_rule.required' => 'Выберите правило для перевозчиков.',
            'deduction_primary_percent.required' => 'Укажите вычет, %.',
            'effective_from.required' => 'Укажите дату начала действия.',
            'effective_to.after_or_equal' => 'Дата окончания не может быть раньше даты начала.',
        ];
    }
}
