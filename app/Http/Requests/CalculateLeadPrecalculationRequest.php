<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculateLeadPrecalculationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['draft', 'ready', 'archived'])],
            'freight' => ['nullable', 'array'],
            'freight.to_border_total' => ['nullable', 'numeric', 'min:0'],
            'freight.after_border_total' => ['nullable', 'numeric', 'min:0'],
            'freight.distribution_basis' => ['nullable', Rule::in(['invoice_rub', 'weight_kg', 'equal'])],
            'goods_lines' => ['nullable', 'array'],
            'goods_lines.*.id' => ['nullable', 'string', 'max:64'],
            'goods_lines.*.description' => ['nullable', 'string', 'max:255'],
            'goods_lines.*.tn_ved_code' => ['nullable', 'string', 'max:20'],
            'goods_lines.*.tn_ved_label' => ['nullable', 'string', 'max:255'],
            'goods_lines.*.quantity' => ['nullable', 'integer', 'min:1'],
            'goods_lines.*.weight_kg' => ['nullable', 'numeric', 'min:0'],
            'goods_lines.*.invoice_amount' => ['nullable', 'numeric', 'min:0'],
            'goods_lines.*.currency' => ['nullable', 'string', 'max:8'],
            'goods_lines.*.exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'goods_lines.*.freight_to_border' => ['nullable', 'numeric', 'min:0'],
            'goods_lines.*.freight_after_border' => ['nullable', 'numeric', 'min:0'],
            'goods_lines.*.other_costs' => ['nullable', 'numeric', 'min:0'],
            'goods_lines.*.vehicle_age_years' => ['nullable', 'integer', 'min:0', 'max:50'],
            'goods_lines.*.include_utilization_fee' => ['nullable', 'boolean'],
            'goods_lines.*.duty_percent_override' => ['nullable', 'numeric', 'min:0'],
            'goods_lines.*.vat_percent_override' => ['nullable', 'numeric', 'min:0'],
            'service_lines' => ['nullable', 'array'],
            'service_lines.*.id' => ['nullable', 'string', 'max:64'],
            'service_lines.*.kind' => ['nullable', Rule::in(['logistics', 'other'])],
            'service_lines.*.title' => ['nullable', 'string', 'max:255'],
            'service_lines.*.stage' => ['nullable', 'string', 'max:50'],
            'service_lines.*.amount' => ['nullable', 'numeric', 'min:0'],
            'service_lines.*.currency' => ['nullable', 'string', 'max:8'],
        ];
    }
}
