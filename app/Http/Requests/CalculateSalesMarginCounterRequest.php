<?php

namespace App\Http\Requests;

use App\Services\SalesMarginCounterService;
use App\Support\PaymentFormDictionary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculateSalesMarginCounterRequest extends FormRequest
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
            'anchor_field' => ['nullable', 'string', Rule::in(SalesMarginCounterService::ANCHOR_FIELDS)],
            'customer_without_vat' => ['nullable', 'numeric', 'min:0'],
            'customer_with_vat' => ['nullable', 'numeric', 'min:0'],
            'carrier_without_vat' => ['nullable', 'numeric', 'min:0'],
            'carrier_with_vat' => ['nullable', 'numeric', 'min:0'],
            'additional_expenses' => ['nullable', 'numeric', 'min:0'],
            'insurance' => ['nullable', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'customer_payment_form' => ['nullable', 'string', 'max:50', Rule::in(PaymentFormDictionary::allowedCodesForValidation())],
            'carrier_payment_form' => ['nullable', 'string', 'max:50', Rule::in(PaymentFormDictionary::allowedCodesForValidation())],
            'min_margin_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'order_date' => ['nullable', 'date'],
        ];
    }
}
