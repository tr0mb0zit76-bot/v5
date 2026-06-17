<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculateImportCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $currencyCodes = collect(config('import_cost_calculator.currencies', []))
            ->pluck('code')
            ->filter()
            ->all();

        return [
            'invoice_amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999'],
            'currency' => ['required', 'string', Rule::in($currencyCodes)],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.0001', 'max:999999'],
            'tn_ved_code' => ['required', 'string', 'max:20'],
            'freight_to_border' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'freight_after_border' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'other_costs' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'vehicle_age_years' => ['nullable', 'integer', 'min:0', 'max:50'],
            'include_utilization_fee' => ['nullable', 'boolean'],
            'duty_percent_override' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'vat_percent_override' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invoice_amount.required' => 'Укажите инвойсную стоимость.',
            'tn_ved_code.required' => 'Выберите код ТН ВЭД.',
            'currency.required' => 'Выберите валюту инвойса.',
        ];
    }
}
